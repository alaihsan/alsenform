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
        'started_at',
        'expires_at',
        'is_locked',
        'blur_count',
        'blur_logs',
        'locked_at',
        'unlocked_at',
        'submitted_at',
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
