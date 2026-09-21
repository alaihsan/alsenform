<?php

use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/settings/profile');

    $response->assertOk();
});

test('profile information can be updated with academic fields and avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'role' => 'guru',
    ]);

    $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

    $response = $this
        ->actingAs($user)
        ->post('/settings/profile', [
            'name' => 'Ustadz Ahmad, M.Pd.',
            'email' => 'ahmad@sekolah.sch.id',
            'nip' => '198501012010011001',
            'phone' => '081234567890',
            'subject' => 'Pendidikan Agama Islam',
            'school_origin' => 'SMA Al-Ihsan',
            'avatar' => $file,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->name)->toBe('Ustadz Ahmad, M.Pd.');
    expect($user->email)->toBe('ahmad@sekolah.sch.id');
    expect($user->nip)->toBe('198501012010011001');
    expect($user->phone)->toBe('081234567890');
    expect($user->subject)->toBe('Pendidikan Agama Islam');
    expect($user->school_origin)->toBe('SMA Al-Ihsan');
    expect($user->avatar)->not->toBeNull();
    expect($user->avatar_url)->toContain('storage/');

    Storage::disk('public')->assertExists($user->avatar);
});

test('avatar can be removed', function () {
    Storage::fake('public');

    $avatarPath = 'avatars/sample.jpg';
    Storage::disk('public')->put($avatarPath, 'sample-content');

    $user = User::factory()->create([
        'avatar' => $avatarPath,
    ]);

    $response = $this
        ->actingAs($user)
        ->post('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'remove_avatar' => true,
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

    $user->refresh();
    expect($user->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($avatarPath);
});

test('student cannot delete their account', function () {
    $student = User::factory()->create([
        'role' => 'siswa',
        'nis' => '12345678',
        'password' => Hash::make('password'),
    ]);

    $response = $this
        ->actingAs($student)
        ->from('/settings/profile')
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($student->fresh())->not->toBeNull();
});

test('teacher can delete their account', function () {
    $teacher = User::factory()->create([
        'role' => 'guru',
        'password' => Hash::make('password'),
    ]);

    $response = $this
        ->actingAs($teacher)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($teacher->fresh())->toBeNull();
});

test('user can logout of other browser sessions', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    // Insert dummy session in database
    DB::table('sessions')->insert([
        'id' => 'other-session-token',
        'user_id' => $user->id,
        'ip_address' => '192.168.1.50',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'payload' => 'payload-data',
        'last_activity' => time() - 3600,
    ]);

    $response = $this
        ->actingAs($user)
        ->post('/settings/profile/sessions/logout-other', [
            'password' => 'password123',
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    // Verify other session was removed
    $remaining = DB::table('sessions')->where('id', 'other-session-token')->count();
    expect($remaining)->toBe(0);
});

test('teacher can update default quiz preferences', function () {
    $teacher = User::factory()->create([
        'role' => 'guru',
    ]);

    $response = $this
        ->actingAs($teacher)
        ->patch('/settings/profile/preferences', [
            'default_kkm' => 80,
            'default_duration' => 90,
            'default_shuffle_questions' => true,
            'default_shuffle_options' => true,
            'default_anti_cheat_blur' => true,
            'default_school_name' => 'Madrasah Aliyah Al-Ihsan',
            'default_arabic_font' => 'Scheherazade New',
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $teacher->refresh();
    expect($teacher->quiz_preferences)->toBeArray();
    expect($teacher->quiz_preferences['default_kkm'])->toEqual(80);
    expect($teacher->quiz_preferences['default_duration'])->toEqual(90);
    expect($teacher->quiz_preferences['default_school_name'])->toBe('Madrasah Aliyah Al-Ihsan');
    expect($teacher->quiz_preferences['default_arabic_font'])->toBe('Scheherazade New');
});

test('teacher can set and update proctor pin', function () {
    $teacher = User::factory()->create([
        'role' => 'guru',
        'password' => Hash::make('password123'),
    ]);

    $response = $this
        ->actingAs($teacher)
        ->post('/settings/profile/proctor-pin', [
            'current_password' => 'password123',
            'pin' => '654321',
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $teacher->refresh();
    expect($teacher->has_proctor_pin)->toBeTrue();
    expect(Hash::check('654321', $teacher->proctor_pin))->toBeTrue();
});

test('teacher can export their quizzes to json', function () {
    $teacher = User::factory()->create([
        'role' => 'guru',
    ]);

    QuizForm::create([
        'user_id' => $teacher->id,
        'title' => 'Ujian Akhir Semester PAI',
        'slug' => 'uas-pai-2026',
        'template' => 'exam',
        'questions' => [
            [
                'type' => 'multiple_choice',
                'question' => 'Berapa rukun iman?',
                'options' => ['4', '5', '6', '7'],
                'correct_answer' => '6',
                'points' => 10,
            ],
        ],
        'settings' => [
            'kkm' => 75,
            'duration_minutes' => 60,
        ],
    ]);

    $response = $this
        ->actingAs($teacher)
        ->get('/settings/profile/export-quizzes');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/json');
    $content = $response->streamedContent();
    $data = json_decode($content, true);

    expect($data)->toHaveKey('quizzes');
    expect($data['total_quizzes'])->toBe(1);
    expect($data['quizzes'][0]['title'])->toBe('Ujian Akhir Semester PAI');
});
