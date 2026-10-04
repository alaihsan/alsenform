<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/password');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/settings/password');
});

test('students changing the temporary password on first login continue to the home page', function () {
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '2024201', 'must_change_password' => true]);

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.edit'));

    $this->get(route('password.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Password')
            ->where('auth.user.must_change_password', true)
        );

    $this->from(route('password.edit'))
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($student->refresh()->must_change_password)->toBeFalse();

    $this->get(route('dashboard'))->assertOk();
});
