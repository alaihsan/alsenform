<?php

namespace App\Http\Controllers;

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\QuizSessionSnapshot;
use App\Support\DisplayTime;
use App\Support\HtmlToPlainText;
use App\Support\QuizFormPayloads;
use App\Support\QuizItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ResponseHistoryController extends Controller
{
    /**
     * What the server had of a student's answers during the exam (a copy every few minutes) and
     * what was handed in, with its receipt: to look into "my answers are gone" with facts.
     */
    public function __invoke(QuizForm $quizForm, QuizResponse $quizResponse, QuizFormPayloads $payloads, HtmlToPlainText $plainText): JsonResponse
    {
        Gate::authorize('update', $quizForm);
        abort_unless($quizResponse->quiz_form_id === $quizForm->id, 404);

        $questions = collect(QuizItems::questions($quizForm->questions))->values();

        /** @param array<int|string, mixed> $answers */
        $describe = fn (array $answers): array => $questions
            ->map(fn (array $question, int $index): array => [
                'number' => $index + 1,
                'title' => Str::limit(trim((string) preg_replace('/\s+/u', ' ', $plainText->convert((string) ($question['title'] ?? '')))), 80),
                'answer' => implode(', ', $payloads->answerLabels($question, $answers[$question['id'] ?? ''] ?? null)),
            ])
            ->filter(fn (array $answer): bool => $answer['answer'] !== '')
            ->values()
            ->all();

        $snapshots = $quizResponse->quiz_session_id !== null
            ? QuizSessionSnapshot::query()->where('quiz_session_id', $quizResponse->quiz_session_id)->orderBy('created_at')->orderBy('id')->get()
            : collect();
        $answers = is_array($quizResponse->answers) ? $quizResponse->answers : [];

        return response()->json([
            'receipt' => $quizResponse->receiptCode(),
            'receiptIntact' => $quizResponse->hasIntactReceipt(),
            'submittedAt' => DisplayTime::format($quizResponse->created_at, 'd M Y H:i:s'),
            'submitAttempts' => $quizResponse->submit_attempts,
            'maxSyncDelaySeconds' => $quizResponse->quizSession?->max_sync_delay_seconds,
            'final' => ['answered' => QuizSession::countAnswered($answers), 'answers' => $describe($answers)],
            'snapshots' => $snapshots->map(fn (QuizSessionSnapshot $snapshot): array => [
                'id' => $snapshot->id,
                'time' => DisplayTime::format($snapshot->created_at, 'H:i:s'),
                'answered' => $snapshot->answered,
                'answers' => $describe(is_array($snapshot->answers) ? $snapshot->answers : []),
            ])->values()->all(),
        ]);
    }
}
