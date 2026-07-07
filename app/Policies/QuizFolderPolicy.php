<?php

namespace App\Policies;

use App\Models\QuizFolder;
use App\Models\User;

class QuizFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, QuizFolder $quizFolder): bool
    {
        return $user->is($quizFolder->user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, QuizFolder $quizFolder): bool
    {
        return $user->is($quizFolder->user);
    }

    public function delete(User $user, QuizFolder $quizFolder): bool
    {
        return $user->is($quizFolder->user);
    }

    public function restore(User $user, QuizFolder $quizFolder): bool
    {
        return $user->is($quizFolder->user);
    }

    public function forceDelete(User $user, QuizFolder $quizFolder): bool
    {
        return $user->is($quizFolder->user);
    }
}
