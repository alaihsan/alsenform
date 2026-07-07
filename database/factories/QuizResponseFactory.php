<?php

namespace Database\Factories;

use App\Models\QuizForm;
use App\Models\QuizResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizResponse>
 */
class QuizResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_form_id' => QuizForm::factory(),
            'email' => fake()->safeEmail(),
            'answers' => [
                1 => fake()->randomElement(['Option 1', 'Option 2']),
            ],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
