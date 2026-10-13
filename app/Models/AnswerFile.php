<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file a student handed in for a "File upload" question, kept on the school server (the local
 * disk, outside the public folder) and only given to the exam's teachers.
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
    ];

    public function quizForm(): BelongsTo
    {
        return $this->belongsTo(QuizForm::class);
    }

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }

    /**
     * The file as an answer value: what the page shows, without where it is stored.
     *
     * @return array{id: string, name: string, size: int, type: string|null}
     */
    public function asAnswer(): array
    {
        return ['id' => $this->id, 'name' => $this->original_name, 'size' => (int) $this->size, 'type' => $this->mime_type];
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
