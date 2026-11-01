<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

/*
 * Students sign in with their default password (the last 6 digits of the NIS) and go straight
 * to the dashboard: changing it on the first login is no longer required.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
});

test('a student added by the admin is not asked to change the default password', function () {
    $this->actingAs($this->admin)->post(route('users.store'), [
        'role' => 'siswa',
        'name' => 'Ahmad Siswa',
        'nis' => '1234567890',
        'kelas' => 'X-A',
    ])->assertRedirect();

    $student = User::where('nis', '1234567890')->sole();
    expect($student->must_change_password)->toBeFalse();

    $this->actingAs($student)->get(route('dashboard'))->assertSuccessful();
});

test('students imported from a CSV file are not asked to change the default password', function (string $route) {
    $csv = UploadedFile::fake()->createWithContent('students.csv', "NIS,NAMA,KELAS\n2025001122,Adit,X-A\n2025003344,Dennis,X-B");

    $this->actingAs($this->admin)->post(route($route), ['file' => $csv])->assertRedirect();

    expect(User::whereIn('nis', ['2025001122', '2025003344'])->pluck('must_change_password')->all())->toBe([false, false]);

    $this->actingAs(User::where('nis', '2025001122')->sole())->get(route('dashboard'))->assertSuccessful();
})->with(['students.import', 'users.import-students']);

test('a password reset to the default does not ask the student to change it again', function (string $route) {
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '9988771122', 'password' => Hash::make('customRandomPass')]);

    $this->actingAs($this->admin)->post(route($route, $student))->assertRedirect();

    $student->refresh();
    expect(Hash::check('771122', $student->password))->toBeTrue()
        ->and($student->must_change_password)->toBeFalse();

    $this->actingAs($student)->get(route('dashboard'))->assertSuccessful();
})->with(['students.reset-password', 'users.reset-password']);

test('a student signs in with the default password and lands on the dashboard', function () {
    User::factory()->create(['role' => 'siswa', 'nis' => '2024201', 'email' => null, 'password' => Hash::make('024201')]);

    $this->post(route('login'), ['identifier' => '2024201', 'password' => '024201'])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertSuccessful();
});

test('an account still flagged from before is not sent to the password page', function () {
    $student = User::factory()->create(['role' => 'siswa', 'must_change_password' => true]);

    $this->actingAs($student)->get(route('dashboard'))->assertSuccessful();
    $this->actingAs($student)->getJson(route('dashboard'))->assertSuccessful();
});

test('the migration clears the flag on every account', function () {
    $flagged = User::factory()->count(2)->create(['role' => 'siswa', 'must_change_password' => true]);
    $teacher = User::factory()->create(['role' => 'guru', 'must_change_password' => false]);

    (require base_path('database/migrations/2026_10_31_145623_clear_must_change_password_on_users.php'))->up();

    expect($flagged->map->fresh()->pluck('must_change_password')->all())->toBe([false, false])
        ->and($teacher->fresh()->must_change_password)->toBeFalse();
});
