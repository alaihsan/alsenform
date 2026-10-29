<?php

namespace Database\Factories;

use App\Models\ServerError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerError>
 */
class ServerErrorFactory extends Factory
{
    /**
     * Define the model's default state: a fault in a controller, seen a few times today.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fingerprint' => sha1(fake()->unique()->uuid()),
            'exception' => 'RuntimeException',
            'message' => 'Something went wrong while saving the answers.',
            'location' => 'app/Http/Controllers/QuizResponseController.php:'.fake()->numberBetween(10, 900),
            'occurrences' => 3,
            'last_reference' => 'ERR-7K3QXA',
            'last_method' => 'POST',
            'last_url' => '/forms/ujian-ipa/responses',
            'last_seen_at' => now(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => ['resolved_at' => now()]);
    }
}
