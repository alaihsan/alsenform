<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;

/**
 * How well the answers of an exam got to the server, in three figures that tell what to improve:
 * the share of students whose answers always reached the server within 30 seconds, the share of
 * hand-ins that went through at the first try, and the errors (blank pages, server errors) per
 * hundred students. Measured since the delivery was recorded (receipts, sync delays).
 */
class ExamDelivery
{
    /**
     * An answer that reaches the server within this many seconds counts as saved in time.
     */
    public const SAVED_WITHIN_SECONDS = 30;

    /**
     * @return array{
     *     attempts: int,
     *     savedInTime: float|null,
     *     savedInTimeOf: int,
     *     slowestSaveSeconds: int|null,
     *     firstTry: float|null,
     *     firstTryOf: int,
     *     autoSubmitted: int,
     *     errors: int,
     *     errorsPer100: float|null,
     * }
     */
    public function of(QuizForm $quizForm): array
    {
        $started = fn () => QuizSession::query()->where('quiz_form_id', $quizForm->id)->whereNotNull('started_at');
        $attempts = $started()->count();

        // Attempts whose page reported how long its answers took (autosave on, a page of this version).
        $measured = $started()->whereNotNull('max_sync_delay_seconds');
        $savedInTimeOf = (clone $measured)->count();
        $savedInTime = (clone $measured)->where('max_sync_delay_seconds', '<=', self::SAVED_WITHIN_SECONDS)->count();

        // Hand-ins sent by the exam page itself (not by the server when the time ran out).
        $handedIn = QuizResponse::query()->where('quiz_form_id', $quizForm->id)->whereNotNull('submit_attempts');
        $firstTryOf = (clone $handedIn)->count();
        $firstTry = (clone $handedIn)->where('submit_attempts', 1)->count();

        $errors = ActivityLog::query()
            ->where('quiz_form_id', $quizForm->id)
            ->whereIn('event', [ActivityLog::PAGE_FAILED, ActivityLog::SERVER_ERROR])
            ->count();

        return [
            'attempts' => $attempts,
            'savedInTime' => $this->percent($savedInTime, $savedInTimeOf),
            'savedInTimeOf' => $savedInTimeOf,
            'slowestSaveSeconds' => $savedInTimeOf > 0 ? (int) $measured->max('max_sync_delay_seconds') : null,
            'firstTry' => $this->percent($firstTry, $firstTryOf),
            'firstTryOf' => $firstTryOf,
            'autoSubmitted' => QuizResponse::query()->where('quiz_form_id', $quizForm->id)->where('is_auto_submitted', true)->count(),
            'errors' => $errors,
            'errorsPer100' => $attempts > 0 ? round($errors / $attempts * 100, 1) : null,
        ];
    }

    protected function percent(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
