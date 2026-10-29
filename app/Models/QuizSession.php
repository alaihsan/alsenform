<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class QuizSession extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quiz_form_id',
        'user_id',
        'session_token',
        'respondent_identifier',
        'respondent_name',
        'respondent_class',
        'respondent_email',
        'started_at',
        'expires_at',
        'is_locked',
        'blur_count',
        'blur_logs',
        'locked_at',
        'unlocked_at',
        'submitted_at',
        'draft_answers',
        'doubtful_question_ids',
        'draft_saved_at',
        'unsynced_since',
        'max_sync_delay_seconds',
        'layout',
        'last_seen_at',
        'left_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'locked_at' => 'datetime',
            'unlocked_at' => 'datetime',
            'submitted_at' => 'datetime',
            'is_locked' => 'boolean',
            'blur_count' => 'integer',
            'blur_logs' => 'array',
            'draft_answers' => 'array',
            'doubtful_question_ids' => 'array',
            'layout' => 'array',
            'last_seen_at' => 'datetime',
            'left_at' => 'datetime',
            'draft_saved_at' => 'datetime',
            'unsynced_since' => 'datetime',
            'max_sync_delay_seconds' => 'integer',
        ];
    }

    /**
     * Peta Kelas flags an attempt whose newest answers have been on the device only for this long.
     */
    public const UNSAVED_AFTER_SECONDS = 120;

    /**
     * Note since when the exam page holds answers the server does not have yet, as the page tells
     * with its status checks: a time in milliseconds of the server's clock, or 0 when it holds none.
     * Kept within the attempt (a device clock far off says nothing) and written only when it changes.
     */
    public function noteUnsyncedSince(int $milliseconds): void
    {
        $since = null;

        if ($milliseconds > 0) {
            $since = Carbon::createFromTimestampMs($milliseconds);
            $since = $since->min(now())->max($this->started_at ?? $since);
        }

        $isSame = $since === null ? $this->unsynced_since === null : $this->unsynced_since?->getTimestamp() === $since->getTimestamp();

        if (! $isSame) {
            $this->forceFill(['unsynced_since' => $since])->saveQuietly();
        }
    }

    /**
     * The server now has the answers the page sent. $pendingSince (milliseconds of the server's
     * clock) is when the oldest of them was given: the longest such wait of the attempt is kept.
     *
     * @return array<string, mixed> The attributes to store with the draft.
     */
    public function arrivedDraftAttributes(?int $pendingSince): array
    {
        $attributes = ['unsynced_since' => null];

        if ($pendingSince !== null && $pendingSince > 0) {
            $delay = (int) max(0, min(6 * 3600, (now()->getTimestampMs() - $pendingSince) / 1000));
            $attributes['max_sync_delay_seconds'] = max($delay, (int) $this->max_sync_delay_seconds);
        }

        return $attributes;
    }

    /**
     * Keep a copy of the answers on the server, at most every few minutes, so what the server had
     * during the exam can be shown when a student says their answers are gone.
     *
     * @param  array<int|string, mixed>  $answers
     */
    public function takeSnapshot(array $answers): void
    {
        $hasRecentCopy = $this->snapshots()
            ->where('created_at', '>', now()->subMinutes(QuizSessionSnapshot::EVERY_MINUTES))
            ->exists();

        if (! $hasRecentCopy && $answers !== []) {
            $this->snapshots()->create(['answers' => $answers, 'answered' => self::countAnswered($answers)]);
        }
    }

    /**
     * How many questions of saved answers are answered (an empty text or no choice is not).
     */
    public static function countAnswered(mixed $answers): int
    {
        return is_array($answers)
            ? count(array_filter($answers, fn (mixed $answer): bool => $answer !== null && $answer !== '' && $answer !== []))
            : 0;
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(QuizSessionSnapshot::class);
    }

    public function recordBlurEvent(?string $ip = null): void
    {
        $logs = $this->blur_logs ?? [];
        $logs[] = [
            'timestamp' => now()->toIso8601String(),
            'ip' => $ip,
        ];

        $this->update([
            'is_locked' => true,
            'locked_at' => now(),
            'blur_count' => ($this->blur_count ?? 0) + 1,
            'blur_logs' => $logs,
            'left_at' => null,
        ]);
    }

    /**
     * Note that the exam page is in touch with the server (at most every 10 seconds). Not after
     * the time ran out: it tells whether the page was online at the end, not when it came back.
     */
    public function markSeen(): void
    {
        if ($this->expires_at !== null && now()->gt($this->expires_at->copy()->addSeconds(60))) {
            return;
        }

        if ($this->last_seen_at === null || $this->last_seen_at->lt(now()->subSeconds(10))) {
            $this->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }

    /**
     * Seconds a reloaded exam page may take to load again before that counts as leaving the exam.
     */
    public const RELOAD_GRACE_SECONDS = 20;

    /**
     * How a returning exam page may have been opened without leaving the exam: a reload of the
     * same tab, or a browser too old to tell.
     */
    public const RELOAD_NAVIGATIONS = ['reload', 'unknown'];

    /**
     * Settle a page that was closed or reloaded. Only a reload of the same tab that is back within
     * a few seconds is not leaving the exam. Away longer, the exam opened anew (the tab was closed),
     * or brought back from the browser history (another site in the same tab) locks the attempt
     * like a tab switch.
     *
     * $returning is how the returning page was opened, told by that page; null for any other
     * request (the page load itself, a poll), which only settles a page that stays away too long.
     */
    public function settleLeave(QuizForm $quizForm, ?string $ip = null, ?string $returning = null): void
    {
        if ($this->left_at === null) {
            return;
        }

        $wasAwayTooLong = $this->left_at->lt(now()->subSeconds(self::RELOAD_GRACE_SECONDS));

        if ($returning === null && ! $wasAwayTooLong) {
            return;
        }

        $this->forceFill(['left_at' => null])->saveQuietly();

        $leftTheExam = $returning === null || ! in_array($returning, self::RELOAD_NAVIGATIONS, true);

        if ($leftTheExam && $quizForm->locksOnBlur() && $this->started_at !== null && $this->submitted_at === null && ! $this->is_locked) {
            $this->recordBlurEvent($ip);
        }
    }

    /**
     * Let the student continue the attempt.
     */
    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'unlocked_at' => now(),
            'left_at' => null,
        ]);
    }

    /**
     * Determine if the attempt is locked right now. The lock only holds while the form still
     * locks on blur (the teacher can switch it off mid-exam) and the answers are not handed in.
     */
    public function isEffectivelyLocked(QuizForm $quizForm): bool
    {
        return $this->is_locked && $this->submitted_at === null && $quizForm->locksOnBlur();
    }

    /**
     * The answers saved on the server (draft, or the copy taken when the attempt was locked) to
     * questions the exam still has.
     *
     * @param  list<string>|null  $onlyQuestionIds  The questions of the attempt, when it got a selection.
     * @return array<int|string, mixed>
     */
    public function savedAnswersFor(QuizForm $quizForm, ?array $onlyQuestionIds = null): array
    {
        $questionIds = $onlyQuestionIds ?? array_map('strval', array_column(is_array($quizForm->questions) ? $quizForm->questions : [], 'id'));

        return array_filter(
            is_array($this->draft_answers) ? $this->draft_answers : [],
            fn (int|string $questionId): bool => in_array((string) $questionId, $questionIds, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function quizForm(): BelongsTo
    {
        return $this->belongsTo(QuizForm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        // Allow 15 seconds grace period for network delays
        return now()->isAfter($this->expires_at->addSeconds(15));
    }
}
