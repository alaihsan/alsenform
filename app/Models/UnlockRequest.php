<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnlockRequest extends Model
{
    protected $fillable = [
        'quiz_form_id',
        'respondent_identifier',
        'email',
        'unlock_code',
        'status',
    ];

    public function quizForm(): BelongsTo
    {
        return $this->belongsTo(QuizForm::class);
    }
}
