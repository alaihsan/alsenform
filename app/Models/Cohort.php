<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cohort extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cohort_user')
            ->withTimestamps();
    }

    public function quizForms(): BelongsToMany
    {
        return $this->belongsToMany(QuizForm::class, 'cohort_quiz_form')
            ->withTimestamps();
    }

    public function hasUser(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->users()->where('users.id', $userId)->exists();
    }
}
