<?php

namespace App\Http\Controllers;

use App\Models\QuizForm;
use App\Services\Addons;
use App\Support\Addon;
use App\Support\ExamToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ExamTokenController extends Controller
{
    /**
     * The token of the Token Ujian add-on for the exam's proctor, polled by the editor when it
     * changes: the current token, when it is replaced, and the one just replaced while it is still
     * accepted.
     */
    public function __invoke(QuizForm $quizForm, ExamToken $examToken, Addons $addons): JsonResponse
    {
        Gate::authorize('update', $quizForm);
        abort_unless($addons->isInstalledFor(Addon::ExamToken, $quizForm->user), 403, 'Add-on Token Ujian belum dipasang untuk ujian ini.');

        $now = now();

        return response()->json([
            'token' => $examToken->current($quizForm, $now),
            'changesAt' => $examToken->changesAt($now)->toISOString(),
            'previousToken' => $examToken->previousStillAccepted($quizForm, $now),
            'previousAcceptedUntil' => $examToken->previousAcceptedUntil($now)?->toISOString(),
            'serverTime' => $now->toISOString(),
            'rotateMinutes' => intdiv(ExamToken::ROTATE_SECONDS, 60),
            'active' => $quizForm->usesAddon(Addon::ExamToken),
        ]);
    }
}
