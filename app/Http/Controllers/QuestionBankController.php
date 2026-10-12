<?php

namespace App\Http\Controllers;

use App\Models\QuizForm;
use App\Services\QuestionBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'items' => collect($page->items())->map(fn ($item): array => $questionBank->present($item, $fingerprintsInExam))->all(),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'hasMore' => $page->hasMorePages(),
            'types' => $questionBank->types($teacher),
            'forms' => $questionBank->forms($teacher),
        ]);
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
