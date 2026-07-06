<?php

namespace Database\Factories;

use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuizForm>
 */
class QuizFormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'description' => fake()->sentence(),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'template' => 'blank',
            'questions' => [
                [
                    'id' => 1,
                    'title' => fake()->sentence(),
                    'description' => '',
                    'type' => 'Multiple choice',
                    'options' => ['Option 1', 'Option 2'],
                    'answer' => '',
                    'required' => true,
                    'media' => [],
                ],
            ],
            'settings' => [
                'collectEmail' => false,
                'showProgress' => true,
                'shuffleQuestions' => false,
            ],
        ];
    }
}
