<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use App\Support\DisplayTime;
use App\Support\PageFailure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * How many of the latest errors the results page lists.
     */
    public const RECENT_ERRORS = 20;

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

        $errors = $this->errors($quizForm)->count();

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

    /**
     * The errors the students met on this exam: pages that failed to load and server errors.
     * What the people who edit the exam met while editing it (saving it, its results) is left out;
     * a failed exam page counts for them too, as when an interviewer fills it in.
     *
     * @return Builder<ActivityLog>
     */
    public function errors(QuizForm $quizForm): Builder
    {
        $editorIds = User::query()
            ->whereKey($quizForm->user_id)
            ->orWhereIn('id', DB::table('quiz_form_collaborators')->where('quiz_form_id', $quizForm->id)->select('user_id'))
            ->orWhere('is_admin', true)
            ->orWhere('role', 'admin')
            ->select('id');

        return ActivityLog::query()
            ->where('quiz_form_id', $quizForm->id)
            ->whereIn('event', [ActivityLog::PAGE_FAILED, ActivityLog::SERVER_ERROR])
            ->where(fn (Builder $query) => $query
                ->whereNull('user_id')
                ->orWhereNotIn('user_id', $editorIds)
                ->orWhere(fn (Builder $examPage) => $examPage->where('event', ActivityLog::PAGE_FAILED)->where('meta->path', '/forms/'.$quizForm->slug)));
    }

    /**
     * The latest errors the students met, for the results page: when, who, on what device and why.
     *
     * @return list<array{id: int, time: string, kind: string, person: string, group: string|null, device: string, cause: string, detail: string|null}>
     */
    public function recentErrors(QuizForm $quizForm): array
    {
        return $this->errors($quizForm)
            ->with('user:id,name,kelas')
            ->latest()
            ->limit(self::RECENT_ERRORS)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'time' => DisplayTime::format($log->created_at, 'd M H:i'),
                'kind' => ActivityLog::EVENT_LABELS[$log->event] ?? $log->event,
                'person' => $log->user?->name ?? ($log->respondent_name ?: 'Peserta tamu'),
                'group' => $log->user?->kelas ?? $log->respondent_class,
                'device' => implode(' · ', array_filter([$log->device, $log->os, $log->browser])) ?: 'Perangkat tidak dikenal',
                'cause' => $log->event === ActivityLog::PAGE_FAILED
                    ? (PageFailure::tryFrom((string) ($log->meta['code'] ?? ''))?->title() ?? 'Halaman gagal dimuat').' ('.($log->meta['code'] ?? '?').')'
                    : 'Galat server '.($log->meta['reference'] ?? '').' (HTTP '.($log->meta['status'] ?? 500).')',
                // What the browser said, or where on the server: for whoever fixes it.
                'detail' => Str::limit((string) ($log->meta['message'] ?? $log->meta['asset'] ?? trim(($log->meta['method'] ?? '').' '.($log->meta['path'] ?? ''))), 220) ?: null,
            ])
            ->all();
    }

    protected function percent(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
