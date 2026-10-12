<?php

namespace Database\Factories;

use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuestionBankItem>
 */
class QuestionBankItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $question = [
            'title' => fake()->sentence().'?',
            'description' => '',
            'type' => 'Multiple choice',
            'options' => ['A', 'B', 'C', 'D'],
            'rows' => [],
            'columns' => [],
            'answer' => 0,
            'required' => true,
            'media' => [],
            'points' => 1,
        ];

        return [
            'user_id' => User::factory(),
            'quiz_form_id' => null,
            'source_question_id' => (string) fake()->unique()->numberBetween(1, 1_000_000),
            'fingerprint' => sha1((string) json_encode($question)),
            'type' => $question['type'],
            'search_text' => Str::lower($question['title'].' '.implode(' ', $question['options'])),
            'question' => $question,
        ];
    }

    /**
     * Kept from the given exam, owned by its teacher.
     */
    public function fromExam(QuizForm $quizForm): static
    {
        return $this->state(fn (): array => [
            'user_id' => $quizForm->user_id,
            'quiz_form_id' => $quizForm->id,
        ]);
    }
}
