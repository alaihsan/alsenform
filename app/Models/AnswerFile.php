<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file a student handed in for a "File upload" question, or the recording of a "Voice answer"
 * question, kept on the school server (the local disk, outside the public folder) and only given
 * to the exam's teachers.
 */
class AnswerFile extends Model
{
    use HasUuids;

    protected $fillable = [
        'quiz_form_id',
        'quiz_session_id',
        'question_id',
        'path',
        'original_name',
        'mime_type',
        'size',
        'duration',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'float',
        ];
    }

    public function quizForm(): BelongsTo
    {
        return $this->belongsTo(QuizForm::class);
    }

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }

    /**
     * The file as an answer value: what the page shows, without where it is stored. A recording
     * also has its length.
     *
     * @return array{id: string, name: string, size: int, type: string|null, duration?: float}
     */
    public function asAnswer(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'size' => (int) $this->size,
            'type' => $this->mime_type,
            ...($this->duration !== null ? ['duration' => (float) $this->duration] : []),
        ];
    }

    public function isAudio(): bool
    {
        return str_starts_with((string) $this->mime_type, 'audio/');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
