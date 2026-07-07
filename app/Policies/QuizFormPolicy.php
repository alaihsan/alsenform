<?php

namespace App\Policies;

use App\Models\QuizForm;
use App\Models\User;

class QuizFormPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, QuizForm $quizForm): bool
    {
        return $user->is($quizForm->user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, QuizForm $quizForm): bool
    {
        return $user->is($quizForm->user);
    }

    public function delete(User $user, QuizForm $quizForm): bool
    {
        return $user->is($quizForm->user);
    }

    public function restore(User $user, QuizForm $quizForm): bool
    {
        return $user->is($quizForm->user);
    }

    public function forceDelete(User $user, QuizForm $quizForm): bool
    {
        return $user->is($quizForm->user);
    }
}
