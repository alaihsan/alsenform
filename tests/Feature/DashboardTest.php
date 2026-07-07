<?php

use App\Models\QuizForm;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('recentForms', 0)
        );
});

test('dashboard shows recent forms sorted by last update', function () {
    $user = User::factory()->create();
    $olderForm = QuizForm::factory()->for($user)->create([
        'title' => 'Older quiz',
        'slug' => 'older-quiz',
        'updated_at' => now()->subDay(),
    ]);
    $newerForm = QuizForm::factory()->for($user)->create([
        'title' => 'Newer quiz',
        'slug' => 'newer-quiz',
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    expect($response->inertiaProps('recentForms.0.id'))->toBe($newerForm->id)
        ->and($response->inertiaProps('recentForms.1.id'))->toBe($olderForm->id);
});

test('authenticated users can create and open the form editor', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $createResponse = $this->get('/forms/create/blank');
    $quizForm = QuizForm::query()->whereBelongsTo($user)->firstOrFail();

    $createResponse->assertRedirect(route('forms.edit', ['quizForm' => $quizForm->slug]));

    $response = $this->get(route('forms.edit', ['quizForm' => $quizForm->slug]));
    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FormEditor')
            ->where('template', 'blank')
            ->where('quizForm.id', $quizForm->id)
        );
});

test('public quiz link works by unique slug', function () {
    $quizForm = QuizForm::factory()->create([
        'title' => 'Public quiz',
        'slug' => 'public-quiz',
    ]);

    $this->get('/forms/public-quiz')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PublicQuiz')
            ->where('quizForm.title', $quizForm->title)
        );
});

test('users get a warning when public quiz link already exists', function () {
    $user = User::factory()->create();
    $quizForm = QuizForm::factory()->for($user)->create([
        'slug' => 'my-quiz',
    ]);
    QuizForm::factory()->create([
        'slug' => 'used-quiz',
    ]);

    $this->actingAs($user);

    $this->patch(route('forms.update', $quizForm), [
        'title' => 'Renamed quiz',
        'description' => '',
        'slug' => 'used-quiz',
        'questions' => $quizForm->questions,
        'settings' => $quizForm->settings,
    ])->assertSessionHasErrors([
        'slug' => 'Link sudah digunakan',
    ]);
});
