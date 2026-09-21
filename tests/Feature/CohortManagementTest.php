<?php

use App\Models\Cohort;
use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from cohorts page to login', function () {
    $response = $this->get(route('cohorts.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated teacher can view cohorts list', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'is_admin' => false]);
    $this->actingAs($teacher);

    $cohort = Cohort::create([
        'name' => 'Kelas 7A',
        'code' => 'KLS-7A',
        'description' => 'Kelompok kelas 7A',
        'created_by' => $teacher->id,
    ]);

    $response = $this->get(route('cohorts.index'));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cohorts/Index')
            ->has('cohorts.data', 1)
            ->where('cohorts.data.0.name', 'Kelas 7A')
            ->where('cohorts.data.0.code', 'KLS-7A')
            ->where('stats.total_cohorts', 1)
        );
});

test('teacher can create a new cohort and auto populate from class', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'is_admin' => false]);
    $this->actingAs($teacher);

    // Create 2 students with kelas 8B
    $s1 = User::create([
        'name' => 'Siswa 1',
        'nis' => '111111',
        'kelas' => '8B',
        'password' => Hash::make('111111'),
        'role' => 'siswa',
    ]);
    $s2 = User::create([
        'name' => 'Siswa 2',
        'nis' => '222222',
        'kelas' => '8B',
        'password' => Hash::make('222222'),
        'role' => 'siswa',
    ]);

    $response = $this->post(route('cohorts.store'), [
        'name' => 'Kelas 8B Unggulan',
        'code' => 'KLS-8B-UNG',
        'description' => 'Kelompok 8B',
        'source_class' => '8B',
    ]);

    $response->assertRedirect();

    $cohort = Cohort::where('code', 'KLS-8B-UNG')->first();
    expect($cohort)->not->toBeNull()
        ->and($cohort->name)->toBe('Kelas 8B Unggulan')
        ->and($cohort->users()->count())->toBe(2)
        ->and($cohort->hasUser($s1))->toBeTrue()
        ->and($cohort->hasUser($s2))->toBeTrue();
});

test('teacher can sync cohorts automatically from existing classes', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'is_admin' => false]);
    $this->actingAs($teacher);

    User::create([
        'name' => 'Siswa Kelas 9A',
        'nis' => '333333',
        'kelas' => '9A',
        'password' => Hash::make('333333'),
        'role' => 'siswa',
    ]);
    User::create([
        'name' => 'Siswa Kelas 9B',
        'nis' => '444444',
        'kelas' => '9B',
        'password' => Hash::make('444444'),
        'role' => 'siswa',
    ]);

    $response = $this->post(route('cohorts.sync-from-classes'));
    $response->assertRedirect();

    expect(Cohort::count())->toBeGreaterThanOrEqual(2);
    $c9a = Cohort::where('code', 'KLS-9A')->first();
    expect($c9a)->not->toBeNull()
        ->and($c9a->users()->count())->toBe(1);
});

test('teacher can add and remove members from a cohort', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'is_admin' => false]);
    $this->actingAs($teacher);

    $cohort = Cohort::create([
        'name' => 'Tim Robotik',
        'code' => 'ROBOTIK',
        'created_by' => $teacher->id,
    ]);

    $student = User::create([
        'name' => 'Budi Robotik',
        'nis' => '555555',
        'kelas' => '7B',
        'password' => Hash::make('555555'),
        'role' => 'siswa',
    ]);

    // Add member
    $responseAdd = $this->post(route('cohorts.members.add', $cohort), [
        'user_ids' => [$student->id],
    ]);
    $responseAdd->assertRedirect();
    expect($cohort->fresh()->hasUser($student))->toBeTrue();

    // Remove member
    $responseRemove = $this->delete(route('cohorts.members.remove', [$cohort, $student]));
    $responseRemove->assertRedirect();
    expect($cohort->fresh()->hasUser($student))->toBeFalse();
});

test('teacher can assign cohorts to a quiz form', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'is_admin' => false]);
    $this->actingAs($teacher);

    $cohort1 = Cohort::create(['name' => 'Cohort 1', 'created_by' => $teacher->id]);
    $cohort2 = Cohort::create(['name' => 'Cohort 2', 'created_by' => $teacher->id]);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $teacher->id,
        'published_at' => now(),
    ]);

    $response = $this->patch(route('forms.update', $quizForm), [
        'title' => $quizForm->title,
        'slug' => $quizForm->slug,
        'questions' => $quizForm->questions,
        'settings' => $quizForm->settings,
        'cohort_ids' => [$cohort1->id, $cohort2->id],
    ]);

    $response->assertRedirect();
    expect($quizForm->cohorts()->count())->toBe(2)
        ->and($quizForm->isRestrictedToCohorts())->toBeTrue();
});

test('student in cohort can access restricted quiz', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'is_admin' => false]);

    $cohort = Cohort::create(['name' => 'Cohort IPA', 'created_by' => $teacher->id]);

    $studentIn = User::create([
        'name' => 'Murid IPA',
        'nis' => '666666',
        'kelas' => 'IPA',
        'password' => Hash::make('666666'),
        'role' => 'siswa',
    ]);
    $cohort->users()->attach($studentIn->id);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $teacher->id,
        'published_at' => now(),
    ]);
    $quizForm->cohorts()->attach($cohort->id);

    $this->actingAs($studentIn);

    $response = $this->get(route('forms.public', $quizForm->slug));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PublicQuiz')
            ->where('accessRestricted', false)
        );
});

test('student not in cohort sees access restricted notice', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'is_admin' => false]);

    $cohort = Cohort::create(['name' => 'Cohort Khusus', 'created_by' => $teacher->id]);

    $studentOut = User::create([
        'name' => 'Murid Lain',
        'nis' => '777777',
        'kelas' => 'IPS',
        'password' => Hash::make('777777'),
        'role' => 'siswa',
    ]);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $teacher->id,
        'published_at' => now(),
    ]);
    $quizForm->cohorts()->attach($cohort->id);

    $this->actingAs($studentOut);

    $response = $this->get(route('forms.public', $quizForm->slug));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PublicQuiz')
            ->where('accessRestricted', true)
            ->where('allowedCohorts', ['Cohort Khusus'])
        );
});

test('guest is redirected to login when accessing cohort restricted quiz', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'is_admin' => false]);

    $cohort = Cohort::create(['name' => 'Cohort Wajib', 'created_by' => $teacher->id]);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $teacher->id,
        'published_at' => now(),
    ]);
    $quizForm->cohorts()->attach($cohort->id);

    $response = $this->get(route('forms.public', $quizForm->slug));
    $response->assertRedirect(route('login'));
});
