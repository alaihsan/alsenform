<?php

namespace App\Actions;

use App\Models\QuizForm;

class DuplicateQuizForm
{
    public function handle(QuizForm $quizForm): QuizForm
    {
        // A copy of a remedial or enrichment exam is an exam of its own.
        $duplicate = $quizForm->replicate([
            'slug',
            'published_at',
            'follow_up_of_id',
            'follow_up_kind',
        ]);

        $duplicate->forceFill([
            'title' => $quizForm->title.' (Copy)',
            'slug' => QuizForm::generateComplexSlug(),
            'published_at' => null,
        ])->save();

        return $duplicate;
    }
}
