<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the activity log of the Laporan page: who logged in or took an exam, from which
 * device and browser, and when.
 */
class ActivityRecorder
{
    public function __construct(protected UserAgent $userAgent = new UserAgent) {}

    /**
     * Record an event. A failure (for example the table is missing because the database was
     * not migrated yet) is logged and never stops the login or the exam it describes.
     *
     * @param  array<string, mixed>  $meta
     */
    public function record(
        string $event,
        Request $request,
        ?User $user = null,
        ?QuizForm $quizForm = null,
        ?string $respondentIdentifier = null,
        ?string $respondentEmail = null,
        array $meta = [],
    ): void {
        $agent = $this->userAgent->parse($request->userAgent());

        try {
            // A savepoint when called inside a transaction, so a failed insert cannot abort it.
            DB::transaction(fn () => ActivityLog::query()->create([
                'event' => $event,
                'user_id' => $user?->id,
                'quiz_form_id' => $quizForm?->id,
                'respondent_identifier' => $user ? null : ($respondentIdentifier !== null ? Str::limit($respondentIdentifier, 100, '') : null),
                'respondent_email' => $user ? null : ($respondentEmail !== null && $respondentEmail !== '' ? Str::limit($respondentEmail, 255, '') : null),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $agent['deviceType'],
                'device' => $agent['device'] !== null ? Str::limit($agent['device'], 255, '') : null,
                'os' => $agent['os'] !== null ? Str::limit($agent['os'], 100, '') : null,
                'browser' => $agent['browser'] !== null ? Str::limit($agent['browser'], 100, '') : null,
                'meta' => $meta === [] ? null : $meta,
                'created_at' => now(),
            ]));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
