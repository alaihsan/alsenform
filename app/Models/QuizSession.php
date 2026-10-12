<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'draft_saved_at',
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
            'draft_saved_at' => 'datetime',
        ];
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
        ]);
    }

    /**
     * Let the student continue the attempt.
     */
    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'unlocked_at' => now(),
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
     * @return array<int|string, mixed>
     */
    public function savedAnswersFor(QuizForm $quizForm): array
    {
        $questionIds = array_map('strval', array_column(is_array($quizForm->questions) ? $quizForm->questions : [], 'id'));

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
