<?php

namespace App\Http\Controllers;

use App\Models\QuizForm;
use App\Services\ItemAnalysis;
use App\Services\QuestionBank;
use App\Support\ExamLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Bank Soal" tab of the question import: a teacher's own earlier questions, to reuse.
 */
class QuestionBankController extends Controller
{
    /**
     * One page of the bank of the signed-in teacher, searched and filtered.
     */
    public function index(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:100'],
            'form' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:50'],
            'topic' => ['nullable', 'string', 'max:150'],
            'difficulty' => ['nullable', 'string', Rule::in([...ExamLayout::DIFFICULTIES, 'none'])],
            'archived' => ['nullable', 'boolean'],
            'full' => ['nullable', 'boolean'],
            'exam' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sync' => ['nullable', 'boolean'],
        ]);
        $teacher = $request->user();

        // Opening the tab brings in the questions of exams written before the bank existed.
        if ($request->boolean('sync')) {
            rescue(fn () => $questionBank->syncAllFor($teacher));
        }

        // The exam being edited, to point out the questions it already has.
        $exam = isset($validated['exam']) ? QuizForm::query()->find($validated['exam']) : null;
        $fingerprintsInExam = $exam !== null && $exam->canBeEditedBy($teacher) ? $questionBank->fingerprintsOf($exam) : [];

        $page = $questionBank->search($teacher, $validated);

        return response()->json([
            'items' => collect($page->items())->map(fn ($item): array => $questionBank->present($item, $fingerprintsInExam, $request->boolean('full')))->all(),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'hasMore' => $page->hasMorePages(),
            'types' => $questionBank->types($teacher),
            'forms' => $questionBank->forms($teacher),
            'suggestions' => $questionBank->labelSuggestions($teacher),
        ]);
    }

    /**
     * The Bank Soal page: browse, label, archive and reuse every question written.
     */
    public function page(): Response
    {
        return Inertia::render('QuestionBank/Index');
    }

    /**
     * Label the chosen questions (only the labels sent change).
     */
    public function labels(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer'],
            ...$this->labelRules(),
        ]);

        $changed = $questionBank->setLabels($request->user(), array_map('intval', $validated['ids']), $request->only(QuestionBank::LABELS));

        return response()->json(['changed' => $changed]);
    }

    /**
     * Archive the chosen questions, or bring them back.
     */
    public function archive(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer'],
            'archived' => ['required', 'boolean'],
        ]);

        $changed = $questionBank->setArchived($request->user(), array_map('intval', $validated['ids']), $request->boolean('archived'));

        return response()->json(['changed' => $changed]);
    }

    /**
     * A new exam made of the chosen questions, opened in the editor.
     */
    public function createExam(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        Gate::authorize('create', QuizForm::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $questions = $questionBank->questions($request->user(), array_map('intval', $validated['ids']));
        abort_if($questions === [], 422, 'Soal yang dipilih tidak ditemukan di bank soal.');

        $quizForm = QuizForm::query()->create([
            'user_id' => $request->user()->id,
            'title' => filled($validated['title'] ?? null) ? $validated['title'] : 'Ujian dari Bank Soal',
            'description' => '',
            'slug' => QuizForm::generateComplexSlug(),
            'template' => 'blank',
            'questions' => array_values(array_map(fn (array $question, int $index): array => ['id' => $index + 1, ...$question], $questions, array_keys($questions))),
            'settings' => QuizForm::defaultSettingsFor($request->user()),
        ]);

        rescue(fn () => $questionBank->sync($quizForm));

        return to_route('forms.edit', ['quizForm' => $quizForm->slug]);
    }

    /**
     * Label one question of an exam, from the editor.
     */
    public function questionLabels(Request $request, QuizForm $quizForm, string $questionId, QuestionBank $questionBank): JsonResponse
    {
        abort_unless($quizForm->canBeEditedBy($request->user()), 403);
        $request->validate($this->labelRules());

        $labels = $questionBank->setLabelsOfQuestion($quizForm, $questionId, $request->only(QuestionBank::LABELS));

        abort_if($labels === null, 422, 'Tulis dan simpan soal ini dulu, baru beri label.');

        return response()->json(['labels' => $labels]);
    }

    /**
     * Give the questions of an exam the difficulty found by its item analysis (Sukar is "sulit").
     */
    public function applyAnalysedDifficulty(Request $request, QuizForm $quizForm, ItemAnalysis $itemAnalysis, QuestionBank $questionBank): JsonResponse
    {
        abort_unless($quizForm->canBeEditedBy($request->user()), 403);

        $labelled = 0;
        foreach ($itemAnalysis->analyze($quizForm)['questions'] as $question) {
            if ($question['difficulty'] === null) {
                continue;
            }

            $difficulty = $question['difficulty']['key'] === 'sukar' ? 'sulit' : $question['difficulty']['key'];
            if ($questionBank->setLabelsOfQuestion($quizForm, (string) $question['id'], ['difficulty' => $difficulty]) !== null) {
                $labelled++;
            }
        }

        return response()->json([
            'labelled' => $labelled,
            'labels' => $questionBank->labelsForExam($quizForm),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function labelRules(): array
    {
        return [
            'subject' => ['sometimes', 'nullable', 'string', 'max:100'],
            'grade' => ['sometimes', 'nullable', 'string', 'max:50'],
            'topic' => ['sometimes', 'nullable', 'string', 'max:150'],
            'difficulty' => ['sometimes', 'nullable', 'string', Rule::in(ExamLayout::DIFFICULTIES)],
        ];
    }

    /**
     * The full questions (answer keys included) of the chosen bank items, to add to an exam.
     */
    public function questions(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        return response()->json([
            'questions' => $questionBank->questions($request->user(), array_map('intval', $validated['ids'])),
        ]);
    }
}
