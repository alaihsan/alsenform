<?php

namespace App\Actions;

use App\Models\QuizForm;

class DuplicateQuizForm
{
    public function handle(QuizForm $quizForm): QuizForm
    {
        $duplicate = $quizForm->replicate([
            'slug',
            'published_at',
        ]);

        $duplicate->forceFill([
            'title' => $quizForm->title.' (Copy)',
            'slug' => QuizForm::generateComplexSlug(),
            'published_at' => null,
        ])->save();

        return $duplicate;
    }
}
