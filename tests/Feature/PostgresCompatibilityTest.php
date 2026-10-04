<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('the application runs on postgresql', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

test('student search ignores letter case on postgresql', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    User::factory()->create(['role' => 'siswa', 'name' => 'Budi Santoso', 'nis' => '9001', 'kelas' => 'X IPA 1']);
    User::factory()->create(['role' => 'siswa', 'name' => 'Siti Aminah', 'nis' => '9002', 'kelas' => 'X IPA 1']);

    $this->actingAs($teacher)
        ->get(route('students.index', ['search' => 'bUdI']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.name', 'Budi Santoso'));
});

test('user search ignores letter case and admins are listed first', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'name' => 'Zaenal Admin']);
    User::factory()->create(['role' => 'guru', 'name' => 'Ani Guru', 'nip' => '19800101']);

    $this->actingAs($admin)
        ->get(route('users.index', ['search' => 'ZAENAL']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('users.data.0.name', 'Zaenal Admin'));

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('users.data.0.name', 'Zaenal Admin'));
});
