<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state: a login from Chrome on an Android phone.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => ActivityLog::LOGIN,
            'user_id' => User::factory(),
            'ip_address' => fake()->localIpv4(),
            'user_agent' => 'Mozilla/5.0 (Linux; Android 13; SM-A536E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.6668.100 Mobile Safari/537.36',
            'device_type' => 'mobile',
            'device' => 'Samsung SM-A536E',
            'os' => 'Android 13',
            'browser' => 'Chrome 129.0.6668.100',
            'created_at' => now(),
        ];
    }

    public function logout(): static
    {
        return $this->state(fn (): array => ['event' => ActivityLog::LOGOUT]);
    }

    public function examStarted(?QuizForm $quizForm = null): static
    {
        return $this->state(fn (): array => [
            'event' => ActivityLog::EXAM_STARTED,
            'quiz_form_id' => $quizForm?->id ?? QuizForm::factory(),
        ]);
    }

    public function examSubmitted(?QuizForm $quizForm = null): static
    {
        return $this->state(fn (): array => [
            'event' => ActivityLog::EXAM_SUBMITTED,
            'quiz_form_id' => $quizForm?->id ?? QuizForm::factory(),
        ]);
    }

    /**
     * From a Windows computer with Microsoft Edge.
     */
    public function fromComputer(): static
    {
        return $this->state(fn (): array => [
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.2792.79',
            'device_type' => 'desktop',
            'device' => 'PC Windows',
            'os' => 'Windows 10/11',
            'browser' => 'Microsoft Edge 129.0.2792.79',
        ]);
    }
}
