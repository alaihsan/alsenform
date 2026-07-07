<?php

namespace App\Models;

use Database\Factories\QuizFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizFolder extends Model
{
    /** @use HasFactory<QuizFolderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(QuizForm::class);
    }
}
