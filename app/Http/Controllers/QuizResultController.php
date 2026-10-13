<?php

namespace App\Http\Controllers;

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Services\QuizResultsReport;
use App\Support\MediaUrl;
use App\Support\QuizScoring;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class QuizResultController extends Controller
{
    public function __construct(
        protected QuizScoring $scoring,
        protected QuizResultsReport $report,
        protected MediaUrl $mediaUrl,
    ) {}

    /**
     * A student's results of a quiz, once the teacher shows them: the grade of every attempt, and
     * (shown in full) per question their answer, the right answer, the pembahasan and the
     * teacher's comment. Accounts see their own attempts; guests those of their device.
     */
    public function show(Request $request, QuizForm $quizForm): Response
    {
        abort_unless($quizForm->published_at || $request->user()?->is($quizForm->user), 404);

        $settings = is_array($quizForm->settings) ? $quizForm->settings : [];
        $isShown = (bool) ($settings['showResultsSummary'] ?? false);
        $isFull = $isShown && ($settings['resultsDetail'] ?? 'score') === 'full';
        $responses = $this->responsesOf($request, $quizForm);
        $questions = array_values(array_filter(is_array($quizForm->questions) ? $quizForm->questions : [], fn (mixed $question): bool => is_array($question) && isset($question['id'])));
        $kkm = $quizForm->kkm();

        return Inertia::render('QuizResult', [
            'quiz' => [
                'title' => $quizForm->title,
                'publicUrl' => route('forms.public', ['quizForm' => $quizForm->slug]),
                'themeColorClass' => $settings['themeColorClass'] ?? 'bg-indigo-600',
            ],
            'isShown' => $isShown,
            'isFull' => $isFull,
            'kkm' => $kkm,
            'attempts' => $isShown
                ? $responses->values()->map(fn (QuizResponse $response, int $index): array => $this->attempt($response, $index + 1, $questions, $kkm, $isFull))->all()
                : [],
            'hasAttempts' => $responses->isNotEmpty(),
        ]);
    }

    /**
     * @return Collection<int, QuizResponse>
     */
    protected function responsesOf(Request $request, QuizForm $quizForm): Collection
    {
        $user = $request->user();
        $identifier = $request->cookie('alsen_resp_id');

        if ($user === null && ! (is_string($identifier) && str_starts_with($identifier, 'anon_'))) {
            return collect();
        }

        return $quizForm->responses()
            ->when(
                $user !== null,
                fn ($query) => $query->where('user_id', $user->id),
                fn ($query) => $query->whereNull('user_id')->where('respondent_identifier', $identifier),
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, mixed>
     */
    protected function attempt(QuizResponse $response, int $number, array $questions, float $kkm, bool $isFull): array
    {
        $answers = is_array($response->answers) ? $response->answers : [];
        $manual = is_array($response->manual_scores) ? $response->manual_scores : [];
        $feedback = is_array($response->feedback) ? $response->feedback : [];
        $result = $this->scoring->result($questions, $answers, $response->question_ids, $manual);

        return [
            'number' => $number,
            'submittedAt' => $response->created_at?->toISOString(),
            'score' => $result['score'],
            'max' => $result['max'],
            'grade' => $result['grade'],
            'ungraded' => $result['ungraded'],
            'passed' => $result['grade'] >= $kkm,
            'questions' => $isFull ? $this->questionsOf($response, $questions, $answers, $manual, $feedback) : [],
        ];
    }

    /**
     * The questions the student got, in the exam's order, as the student reviews them.
     *
     * @param  list<array<string, mixed>>  $questions
     * @param  array<int|string, mixed>  $answers
     * @param  array<int|string, mixed>  $manual
     * @param  array<int|string, mixed>  $feedback
     * @return list<array<string, mixed>>
     */
    protected function questionsOf(QuizResponse $response, array $questions, array $answers, array $manual, array $feedback): array
    {
        $given = $this->scoring->questionsGiven($questions, $response->question_ids);
        $normalized = $this->mediaUrl->normalizeQuestions($given) ?? $given;

        return array_values(array_map(function (array $question, int $index) use ($answers, $manual, $feedback): array {
            $id = $question['id'];
            $answer = $answers[$id] ?? null;
            $points = $this->scoring->points($question);
            $graded = is_numeric($manual[$id] ?? null);
            $isCorrect = $graded
                ? ((float) $manual[$id] >= $points ? true : ((float) $manual[$id] <= 0 ? false : null))
                : $this->scoring->isCorrect($question, $answer);

            return [
                'number' => $index + 1,
                'title' => $question['title'] ?? '',
                'type' => $question['type'] ?? '',
                'media' => $question['media'] ?? [],
                'answer' => $this->report->answerText($question, $answer),
                'correctAnswer' => $this->scoring->isAutoScored($question) ? $this->report->answerKeyText($question) : null,
                'isCorrect' => $isCorrect,
                'earned' => $graded ? (float) $manual[$id] : ($this->scoring->isAutoScored($question) ? ($isCorrect ? $points : 0) : null),
                'points' => $points,
                // Essays and files not graded yet.
                'waiting' => ! $graded && $this->scoring->needsGrading($question),
                'explanation' => $question['explanation'] ?? null,
                'feedback' => $feedback[$id] ?? null,
            ];
        }, $normalized, array_keys($normalized)));
    }
}
