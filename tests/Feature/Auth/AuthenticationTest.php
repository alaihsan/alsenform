<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('administrators authenticate using email', function () {
    $user = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

    $response = $this->post('/login', [
        'identifier' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('teachers authenticate using NIP and cannot use email', function () {
    $teacher = User::factory()->create([
        'role' => 'guru',
        'is_admin' => false,
        'nip' => '198501012010011001',
    ]);

    $this->post('/login', [
        'identifier' => $teacher->nip,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->post('/logout');

    $this->post('/login', [
        'identifier' => $teacher->email,
        'password' => 'password',
    ])->assertSessionHasErrors('identifier');

    $this->assertGuest();
});

test('students authenticate using NIS and cannot use email', function () {
    $student = User::factory()->create([
        'role' => 'siswa',
        'is_admin' => false,
        'nis' => '00202401001',
    ]);

    $this->post('/login', [
        'identifier' => $student->nis,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->post('/logout');

    $this->post('/login', [
        'identifier' => $student->email,
        'password' => 'password',
    ])->assertSessionHasErrors('identifier');

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
