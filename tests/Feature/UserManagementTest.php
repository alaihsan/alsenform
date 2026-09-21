<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from users page to login', function () {
    $response = $this->get(route('users.index'));
    $response->assertRedirect(route('login'));
});

test('superadmin can view users dashboard with stats and roles', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
    ]);
    $this->actingAs($admin);

    $teacher = User::create([
        'name' => 'Guru Matematika',
        'email' => 'guru@sekolah.sch.id',
        'password' => Hash::make('password123'),
        'role' => 'guru',
        'is_admin' => false,
    ]);

    $student = User::create([
        'name' => 'Murid Cerdas',
        'nis' => '2024010099',
        'kelas' => 'XII IPA 1',
        'password' => Hash::make('010099'),
        'role' => 'siswa',
        'is_admin' => false,
    ]);

    $response = $this->get(route('users.index'));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 3)
            ->where('stats.total_users', 3)
            ->where('stats.total_admins', 1)
            ->where('stats.total_teachers', 1)
            ->where('stats.total_students', 1)
            ->where('stats.total_classes', 1)
        );
});

test('superadmin can create a new admin user', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->post(route('users.store'), [
        'role' => 'admin',
        'name' => 'Admin Baru',
        'email' => 'adminbaru@sekolah.sch.id',
        'password' => 'secret123',
    ]);

    $response->assertRedirect();
    $newUser = User::where('email', 'adminbaru@sekolah.sch.id')->first();

    expect($newUser)->not->toBeNull()
        ->and($newUser->role)->toBe('admin')
        ->and($newUser->is_admin)->toBeTrue()
        ->and($newUser->isAdmin())->toBeTrue()
        ->and(Hash::check('secret123', $newUser->password))->toBeTrue();
});

test('superadmin can create a new teacher user', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->post(route('users.store'), [
        'role' => 'guru',
        'name' => 'Ibu Guru Ani',
        'email' => 'ani@sekolah.sch.id',
        'password' => 'guru12345',
    ]);

    $response->assertRedirect();
    $teacher = User::where('email', 'ani@sekolah.sch.id')->first();

    expect($teacher)->not->toBeNull()
        ->and($teacher->role)->toBe('guru')
        ->and($teacher->is_admin)->toBeFalse()
        ->and($teacher->isTeacher())->toBeTrue()
        ->and(Hash::check('guru12345', $teacher->password))->toBeTrue();
});

test('superadmin can create a new student user with default 6 digit NIS password', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->post(route('users.store'), [
        'role' => 'siswa',
        'name' => 'Ahmad Siswa',
        'nis' => '1234567890',
        'kelas' => 'X-A',
    ]);

    $response->assertRedirect();
    $student = User::where('nis', '1234567890')->first();

    expect($student)->not->toBeNull()
        ->and($student->role)->toBe('siswa')
        ->and($student->is_admin)->toBeFalse()
        ->and($student->kelas)->toBe('X-A')
        ->and(Hash::check('567890', $student->password))->toBeTrue();
});

test('superadmin can update user role directly to admin or guru or siswa', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->actingAs($admin);

    $teacher = User::create([
        'name' => 'Pak Joko',
        'email' => 'joko@sekolah.sch.id',
        'password' => Hash::make('password123'),
        'role' => 'guru',
        'is_admin' => false,
    ]);

    // Promote guru to admin
    $response = $this->patch(route('users.update-role', $teacher), [
        'role' => 'admin',
    ]);

    $response->assertRedirect();
    $teacher->refresh();
    expect($teacher->role)->toBe('admin')
        ->and($teacher->is_admin)->toBeTrue();

    // Demote admin back to guru
    $response = $this->patch(route('users.update-role', $teacher), [
        'role' => 'guru',
    ]);

    $response->assertRedirect();
    $teacher->refresh();
    expect($teacher->role)->toBe('guru')
        ->and($teacher->is_admin)->toBeFalse();
});

test('superadmin cannot demote their own admin role', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->patch(route('users.update-role', $admin), [
        'role' => 'guru',
    ]);

    $response->assertSessionHasErrors('error');
    $admin->refresh();
    expect($admin->role)->toBe('admin')
        ->and($admin->is_admin)->toBeTrue();
});

test('superadmin can change password for any user', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->actingAs($admin);

    $teacher = User::create([
        'name' => 'Guru Seni',
        'email' => 'seni@sekolah.sch.id',
        'password' => Hash::make('oldpassword'),
        'role' => 'guru',
        'is_admin' => false,
    ]);

    $response = $this->post(route('users.change-password', $teacher), [
        'password' => 'newSecret999',
    ]);

    $response->assertRedirect();
    $teacher->refresh();
    expect(Hash::check('newSecret999', $teacher->password))->toBeTrue();
});

test('superadmin can reset student password to default 6 digit NIS', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->actingAs($admin);

    $student = User::create([
        'name' => 'Siswa Reset',
        'nis' => '9988771122',
        'kelas' => 'XI IPS 1',
        'password' => Hash::make('customRandomPass'),
        'role' => 'siswa',
        'is_admin' => false,
    ]);

    $response = $this->post(route('users.reset-password', $student));
    $response->assertRedirect();

    $student->refresh();
    expect(Hash::check('771122', $student->password))->toBeTrue();
});

test('superadmin can delete other user but not themselves', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->actingAs($admin);

    $otherUser = User::create([
        'name' => 'User Lain',
        'email' => 'lain@sekolah.sch.id',
        'password' => Hash::make('password123'),
        'role' => 'guru',
        'is_admin' => false,
    ]);

    // Cannot delete self
    $responseSelf = $this->delete(route('users.destroy', $admin));
    $responseSelf->assertSessionHasErrors('error');
    expect(User::find($admin->id))->not->toBeNull();

    // Can delete other
    $responseOther = $this->delete(route('users.destroy', $otherUser));
    $responseOther->assertRedirect();
    expect(User::find($otherUser->id))->toBeNull();
});
