<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('returns a successful response for guests', function () {
    $response = $this->get('/');

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
        );
});

test('authenticated users visiting root are redirected to dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect(route('dashboard'));
});
