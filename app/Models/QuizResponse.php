<?php

namespace App\Models;

use Database\Factories\QuizResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizResponse extends Model
{
    /** @use HasFactory<QuizResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'quiz_form_id',
        'quiz_session_id',
        'user_id',
        'respondent_identifier',
        'respondent_name',
        'respondent_class',
        'email',
        'answers',
        'question_ids',
        'score',
        'is_timeout',
        'is_auto_submitted',
        'late_seconds',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'question_ids' => 'array',
            'late_seconds' => 'integer',
            'score' => 'integer',
            'is_timeout' => 'boolean',
            'is_auto_submitted' => 'boolean',
        ];
    }

    public function quizForm(): BelongsTo
    {
        return $this->belongsTo(QuizForm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }
}
