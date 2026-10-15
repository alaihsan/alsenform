<?php

namespace App\Http\Controllers;

use App\Models\AnswerFile;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Support\QuizItems;
use App\Support\QuizScoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Grading by hand, one question for every student at a time: essays and files, which only the
 * teacher can grade, and short answers, whose key may judge an answer too strictly.
 */
class GradingController extends Controller
{
    public function __construct(protected QuizScoring $scoring) {}

    /**
     * The questions to grade with how many answers wait, and the answers to one of them.
     */
    public function index(Request $request, QuizForm $quizForm): JsonResponse
    {
        Gate::authorize('update', $quizForm);

        $questions = $this->gradableQuestions($quizForm);
        $responses = $quizForm->responses()
            ->with('user:id,name,nis,kelas')
            ->orderBy('id')
            ->get(['id', 'quiz_form_id', 'user_id', 'respondent_identifier', 'respondent_name', 'respondent_class', 'answers', 'question_ids', 'manual_scores', 'feedback']);
        $attempts = QuizResponse::attemptNumbers($responses);

        $summaries = $questions->map(function (array $question) use ($responses): array {
            $given = $responses->filter(fn (QuizResponse $response): bool => $this->wasGiven($response, $question));
            $graded = $given->filter(fn (QuizResponse $response): bool => is_numeric($response->manual_scores[$question['id']] ?? null))->count();

            return [
                'id' => $question['id'],
                'number' => $question['number'],
                'title' => $question['title'] ?? '',
                'type' => $question['type'],
                'points' => $this->scoring->points($question),
                'answers' => $given->count(),
                'graded' => $graded,
                // Essays and files wait for the teacher; short answers are scored by their key.
                'waiting' => $this->scoring->needsGrading($question) ? $given->count() - $graded : 0,
            ];
        })->values();

        $selectedId = $request->query('question');
        $selected = $questions->first(fn (array $question): bool => (string) $question['id'] === (string) $selectedId)
            ?? $questions->first(fn (array $question): bool => ($summaries->firstWhere('id', $question['id'])['waiting'] ?? 0) > 0)
            ?? $questions->first();

        return response()->json([
            'questions' => $summaries->all(),
            'question' => $selected !== null ? [
                'id' => $selected['id'],
                'number' => $selected['number'],
                'title' => $selected['title'] ?? '',
                'description' => $selected['description'] ?? '',
                'type' => $selected['type'],
                'points' => $this->scoring->points($selected),
                'answerKey' => ($selected['type'] ?? '') === 'Short answer' && is_scalar($selected['answer'] ?? null) ? (string) $selected['answer'] : null,
                'explanation' => $selected['explanation'] ?? null,
            ] : null,
            'answers' => $selected !== null ? $this->answersTo($quizForm, $selected, $responses, $attempts) : [],
        ]);
    }

    /**
     * Grade one answer: the points (empty: not graded) and a comment for the student.
     */
    public function update(Request $request, QuizForm $quizForm, QuizResponse $quizResponse): JsonResponse
    {
        Gate::authorize('update', $quizForm);
        abort_unless($quizResponse->quiz_form_id === $quizForm->id, 404);

        $question = $this->gradableQuestions($quizForm)->first(fn (array $question): bool => (string) $question['id'] === (string) $request->input('question_id'));
        abort_if($question === null, 422, 'Soal ini tidak dinilai manual.');
        $points = $this->scoring->points($question);

        $validated = $request->validate([
            'question_id' => ['required'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:'.$points],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ], [
            'score.max' => "Nilai paling besar {$points}.",
            'score.min' => 'Nilai tidak boleh negatif.',
            'score.numeric' => 'Nilai harus berupa angka.',
        ]);

        // Two teachers may grade the same student at once (another question): one change at a time.
        $graded = DB::transaction(function () use ($quizResponse, $question, $validated): QuizResponse {
            $response = QuizResponse::query()->lockForUpdate()->findOrFail($quizResponse->id);
            $scores = is_array($response->manual_scores) ? $response->manual_scores : [];
            $feedback = is_array($response->feedback) ? $response->feedback : [];
            $id = (string) $question['id'];

            if (is_numeric($validated['score'] ?? null)) {
                $scores[$id] = round((float) $validated['score'], 2);
            } else {
                unset($scores[$id]);
            }

            $comment = trim((string) ($validated['feedback'] ?? ''));
            if ($comment !== '') {
                $feedback[$id] = $comment;
            } else {
                unset($feedback[$id]);
            }

            $response->forceFill(['manual_scores' => $scores ?: null, 'feedback' => $feedback ?: null])->save();

            return $response;
        });

        $result = $this->scoring->result(
            is_array($quizForm->questions) ? $quizForm->questions : [],
            is_array($graded->answers) ? $graded->answers : [],
            $graded->question_ids,
            $graded->manual_scores,
        );

        return response()->json([
            'score' => $graded->manual_scores[$question['id']] ?? null,
            'feedback' => $graded->feedback[$question['id']] ?? null,
            'grade' => $result['grade'],
            'ungraded' => $result['ungraded'],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function gradableQuestions(QuizForm $quizForm): Collection
    {
        return collect(QuizItems::questions($quizForm->questions))
            ->map(fn (array $question, int $index): array => [...$question, 'number' => $index + 1, 'type' => $question['type'] ?? '', 'title' => $question['title'] ?? ''])
            ->filter(fn (array $question): bool => $this->scoring->isGradableByHand($question))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $question
     */
    protected function wasGiven(QuizResponse $response, array $question): bool
    {
        return $response->question_ids === null || in_array((string) $question['id'], array_map('strval', $response->question_ids), true);
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  Collection<int, QuizResponse>  $responses
     * @param  array<int, array{attempt: int, attempts: int}>  $attempts
     * @return list<array<string, mixed>>
     */
    protected function answersTo(QuizForm $quizForm, array $question, Collection $responses, array $attempts): array
    {
        $id = $question['id'];
        $isFile = ($question['type'] ?? '') === 'File upload';
        $given = $responses->filter(fn (QuizResponse $response): bool => $this->wasGiven($response, $question));

        $files = $isFile
            ? AnswerFile::query()
                ->where('quiz_form_id', $quizForm->id)
                ->whereIn('id', $given->flatMap(fn (QuizResponse $response): array => collect($response->answers[$id] ?? [])->pluck('id')->filter()->all())->all())
                ->get()
                ->keyBy('id')
            : collect();

        return $given
            ->map(function (QuizResponse $response) use ($id, $question, $isFile, $files, $attempts): array {
                $answer = is_array($response->answers) ? ($response->answers[$id] ?? null) : null;

                return [
                    'responseId' => $response->id,
                    'name' => $response->user?->name ?: ($response->respondent_name ?: 'Anonim'),
                    'kelas' => $response->user?->kelas ?: $response->respondent_class,
                    'attempt' => ($attempts[$response->id]['attempts'] ?? 1) > 1 ? ($attempts[$response->id]['attempt'] ?? 1) : null,
                    'answer' => $isFile ? null : (is_scalar($answer) ? (string) $answer : null),
                    'files' => $isFile
                        ? collect(is_array($answer) ? $answer : [])
                            ->map(fn (mixed $file): ?AnswerFile => is_array($file) ? $files->get($file['id'] ?? '') : null)
                            ->filter()
                            ->map(fn (AnswerFile $file): array => [...$file->asAnswer(), 'url' => route('forms.answer-files.show', [$file->quiz_form_id, $file->id], false), 'isImage' => $file->isImage()])
                            ->values()
                            ->all()
                        : [],
                    // Short answers: what the key said.
                    'autoCorrect' => ($question['type'] ?? '') === 'Short answer' ? $this->scoring->isCorrect($question, $answer) : null,
                    'score' => $response->manual_scores[$id] ?? null,
                    'feedback' => $response->feedback[$id] ?? null,
                    'updateUrl' => route('forms.grading.update', [$response->quiz_form_id, $response->id]),
                ];
            })
            ->sortBy([
                // Answers waiting for a grade first, then by class and name.
                fn (array $a, array $b): int => (is_numeric($a['score']) ? 1 : 0) <=> (is_numeric($b['score']) ? 1 : 0),
                fn (array $a, array $b): int => strnatcasecmp((string) $a['kelas'], (string) $b['kelas']) ?: strnatcasecmp($a['name'], $b['name']) ?: ($a['attempt'] ?? 0) <=> ($b['attempt'] ?? 0),
            ])
            ->values()
            ->all();
    }
}
