<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru', 'nip' => '198001012005011001']);
});

test('teachers open user and cohort management from their settings', function () {
    $this->actingAs($this->teacher)
        ->get(route('students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Students/Index'));

    $this->get(route('cohorts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Cohorts/Index'));
});

test('teachers manage student accounts but not teacher or admin accounts', function () {
    $otherTeacher = User::factory()->create(['role' => 'guru', 'nip' => '198001012005011002']);
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

    $this->actingAs($this->teacher);

    $this->get(route('users.index'))->assertForbidden();
    $this->post(route('users.store'), ['role' => 'admin', 'name' => 'Admin Baru', 'email' => 'baru@example.test', 'password' => 'rahasia123'])->assertForbidden();
    $this->patch(route('users.update-role', $this->teacher), ['role' => 'admin'])->assertForbidden();

    foreach ([$otherTeacher, $admin] as $account) {
        $this->post(route('students.reset-password', $account))->assertForbidden();
        $this->post(route('students.change-password', $account), ['password' => 'diambilalih'])->assertForbidden();
        $this->delete(route('students.destroy', $account))->assertForbidden();
    }

    expect($this->teacher->refresh()->is_admin)->toBeFalse()
        ->and(User::whereKey([$otherTeacher->id, $admin->id])->count())->toBe(2);
});

test('the result of an action is shown on the page as a message', function () {
    $this->actingAs($this->teacher)
        ->from(route('students.index'))
        ->followingRedirects()
        ->post(route('students.store'), ['nis' => '20240555', 'name' => 'Siti Aminah', 'kelas' => 'X-A'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Students/Index')
            ->where('flash.success', fn (string $message) => str_contains($message, 'Siti Aminah') && str_contains($message, '240555'))
        );

    $studentWithoutNis = User::factory()->create(['role' => 'siswa', 'nis' => null]);

    $this->from(route('students.index'))
        ->followingRedirects()
        ->post(route('students.reset-password', $studentWithoutNis))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('errors.error', 'Murid tidak memiliki NIS untuk membuat password default.')
        );
});

test('students cannot open user or cohort management', function () {
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '20240999']);

    $this->actingAs($student)->get(route('students.index'))->assertForbidden();
    $this->get(route('cohorts.index'))->assertForbidden();
});
