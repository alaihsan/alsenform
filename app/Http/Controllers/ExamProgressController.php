<?php

namespace App\Http\Controllers;

use App\Actions\SubmitExpiredAttempts;
use App\Models\QuizForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ExamProgressController extends Controller
{
    /**
     * Live progress of an exam for its teachers, polled by the editor: how many students handed
     * in (each counted once, however many attempts), the answers received, and how many students
     * have the exam open right now.
     */
    public function __invoke(QuizForm $quizForm, SubmitExpiredAttempts $submitExpiredAttempts): JsonResponse
    {
        Gate::authorize('update', $quizForm);

        // Attempts whose time ran out with the page closed count as handed in.
        $submitExpiredAttempts->handleQuietly($quizForm);

        return response()->json([
            'submitted' => $quizForm->submittedStudentCount(),
            'responses' => $quizForm->responses()->count(),
            'working' => $quizForm->workingStudentCount(),
        ]);
    }
}
