<?php

namespace App\Http\Controllers;

use App\Actions\SubmitExpiredAttempts;
use App\Models\QuizForm;
use App\Services\ClassMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ClassMapController extends Controller
{
    /**
     * Peta Kelas, polled by the editor's Keamanan tab while the exam runs: every student of the
     * current sitting with their progress and anything that needs the proctor.
     */
    public function __invoke(QuizForm $quizForm, ClassMap $classMap, SubmitExpiredAttempts $submitExpiredAttempts): JsonResponse
    {
        Gate::authorize('update', $quizForm);

        // Attempts whose time ran out with the page closed show as handed in.
        $submitExpiredAttempts->handleQuietly($quizForm);

        return response()->json($classMap->of($quizForm));
    }
}
