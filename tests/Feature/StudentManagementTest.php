<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from students page to login', function () {
    $response = $this->get(route('students.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated user can view students dashboard', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    User::create([
        'name' => 'Budi Santoso',
        'nis' => '202401001',
        'kelas' => 'X IPA 1',
        'email' => '202401001@student.alsenform.test',
        'password' => Hash::make('401001'),
        'role' => 'siswa',
    ]);

    $response = $this->get(route('students.index'));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Students/Index')
            ->has('students.data', 1)
            ->where('students.data.0.nis', '202401001')
            ->where('students.data.0.name', 'Budi Santoso')
            ->where('students.data.0.kelas', 'X IPA 1')
            ->missing('students.data.0.default_password')
            ->has('classes')
            ->where('stats.total_students', 1)
            ->where('stats.total_classes', 1)
        );
});

test('can create a single student with default 6 digit NIS password', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $response = $this->post(route('students.store'), [
        'nis' => '1029384756',
        'name' => 'Siti Nurhaliza',
        'kelas' => 'XII MIPA 2',
    ]);

    $response->assertRedirect();
    $student = User::where('nis', '1029384756')->first();

    expect($student)->not->toBeNull()
        ->and($student->name)->toBe('Siti Nurhaliza')
        ->and($student->kelas)->toBe('XII MIPA 2')
        ->and($student->email)->toBeNull()
        ->and($student->role)->toBe('siswa');

    // Default password should be last 6 digits of NIS: 384756
    expect(Hash::check('384756', $student->password))->toBeTrue();
});

test('can update student details', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $student = User::create([
        'name' => 'Andi Wijaya',
        'nis' => '9988776655',
        'kelas' => 'X-1',
        'email' => '9988776655@student.alsenform.test',
        'password' => Hash::make('776655'),
        'role' => 'siswa',
    ]);

    $response = $this->put(route('students.update', $student), [
        'nis' => '9988776655',
        'name' => 'Andi Wijaya Pratama',
        'kelas' => 'XI-1',
    ]);

    $response->assertRedirect();
    $student->refresh();

    expect($student->name)->toBe('Andi Wijaya Pratama')
        ->and($student->kelas)->toBe('XI-1');
});

test('can delete student', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $student = User::create([
        'name' => 'Doni',
        'nis' => '1122334455',
        'kelas' => 'IX-A',
        'email' => '1122334455@student.alsenform.test',
        'password' => Hash::make('334455'),
        'role' => 'siswa',
    ]);

    $response = $this->delete(route('students.destroy', $student));
    $response->assertRedirect();

    expect(User::where('nis', '1122334455')->exists())->toBeFalse();
});

test('can reset student password back to 6 digits of NIS', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $student = User::create([
        'name' => 'Rina',
        'nis' => '5544332211',
        'kelas' => 'VII-B',
        'email' => '5544332211@student.alsenform.test',
        'password' => Hash::make('custom-new-password'),
        'role' => 'siswa',
    ]);

    expect(Hash::check('custom-new-password', $student->password))->toBeTrue();

    $response = $this->post(route('students.reset-password', $student));
    $response->assertRedirect();

    $student->refresh();
    // Default password should be last 6 digits of 5544332211 -> 332211
    expect(Hash::check('332211', $student->password))->toBeTrue();
});

test('can preview student import with dry run', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $csvContent = "NIS,NAMA,KELAS\n2025001122,Adit,X-A\n2025003344,Dennis,X-B";

    $response = $this->postJson(route('students.import'), [
        'text' => $csvContent,
        'dry_run' => true,
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'valid_count' => 2,
            'error_count' => 0,
        ])
        ->assertJsonPath('preview.0.nis', '2025001122')
        ->assertJsonMissingPath('preview.0.default_password')
        ->assertJsonPath('preview.1.nis', '2025003344')
        ->assertJsonMissingPath('preview.1.default_password');

    // Ensure not saved yet because dry_run = true
    expect(User::where('nis', '2025001122')->exists())->toBeFalse();
});

test('can import students from CSV file upload', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $csvContent = "NIS,NAMA,KELAS\n1234567890,Radit Maulana,XI IPA 3\n1234567891,Farah Quinn,XI IPA 3";
    $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

    $response = $this->post(route('students.import'), [
        'file' => $file,
    ]);

    $response->assertRedirect();

    $radit = User::where('nis', '1234567890')->first();
    $farah = User::where('nis', '1234567891')->first();

    expect($radit)->not->toBeNull()
        ->and($radit->name)->toBe('Radit Maulana')
        ->and($radit->kelas)->toBe('XI IPA 3')
        ->and($radit->email)->toBeNull()
        ->and(Hash::check('567890', $radit->password))->toBeTrue();

    expect($farah)->not->toBeNull()
        ->and($farah->name)->toBe('Farah Quinn')
        ->and($farah->kelas)->toBe('XI IPA 3')
        ->and($farah->email)->toBeNull()
        ->and(Hash::check('567891', $farah->password))->toBeTrue();
});

test('student can log in using NIS and default 6-digit password without email', function () {
    $student = User::create([
        'name' => 'Bambang Sudarsono',
        'nis' => '202409876543',
        'kelas' => 'XII RPL 1',
        'email' => null,
        'password' => Hash::make('876543'), // Last 6 digits of 202409876543
        'role' => 'siswa',
    ]);

    $response = $this->post(route('login'), [
        'email' => '202409876543', // Log in using NIS into the identifier field
        'password' => '876543',
    ]);

    $this->assertAuthenticatedAs($student);
    $response->assertRedirect(route('dashboard'));
});

test('can download CSV template', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $response = $this->get(route('students.template'));

    $response->assertSuccessful();
    $response->assertHeader('Content-Disposition', 'attachment; filename="template_impor_murid.csv"');
});

test('admin can change student password to custom password', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $student = User::create([
        'name' => 'Fajar Pratama',
        'nis' => '1020304050',
        'kelas' => 'XI MIPA 1',
        'email' => null,
        'password' => Hash::make('304050'),
        'role' => 'siswa',
    ]);

    $response = $this->post(route('students.change-password', $student), [
        'password' => 'rahasia123',
    ]);

    $response->assertRedirect();
    $student->refresh();

    expect(Hash::check('rahasia123', $student->password))->toBeTrue();
});
