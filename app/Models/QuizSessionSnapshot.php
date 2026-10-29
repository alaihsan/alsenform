<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The answers of an attempt as the server had them at a moment of the exam, kept every few minutes
 * (see QuizSession::takeSnapshot()), so a student's "my answers are gone" can be looked into.
 */
class QuizSessionSnapshot extends Model
{
    use MassPrunable;

    /**
     * A copy is kept at most this often per attempt.
     */
    public const EVERY_MINUTES = 5;

    public const UPDATED_AT = null;

    protected $fillable = [
        'quiz_session_id',
        'answers',
        'answered',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'answered' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }

    /**
     * Copies older than the retention period are removed; the handed-in answers stay.
     *
     * @return Builder<QuizSessionSnapshot>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('maintenance.retention_days.draft_snapshots')));
    }
}
