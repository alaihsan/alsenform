<?php

namespace App\Models;

use Database\Factories\QuestionBankItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A question kept in the question bank of the teacher who wrote it (see App\Services\QuestionBank).
 */
class QuestionBankItem extends Model
{
    /** @use HasFactory<QuestionBankItemFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quiz_form_id',
        'source_question_id',
        'fingerprint',
        'type',
        'search_text',
        'question',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The exam the question was written in (also when that exam is in the trash).
     */
    public function quizForm(): BelongsTo
    {
        return $this->belongsTo(QuizForm::class)->withTrashed();
    }
}
