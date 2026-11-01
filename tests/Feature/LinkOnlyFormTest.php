<?php

use App\Models\Cohort;
use App\Models\QuizForm;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A form open to everyone may be kept off the students' Beranda ("Tampilkan di Beranda siswa"
 * off): it is opened by its link only, like an interview the students need not see.
 */

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->student = User::factory()->create(['role' => 'siswa', 'kelas' => '7A']);
    $this->listed = QuizForm::factory()->for($this->teacher)->create(['title' => 'Ulangan Harian', 'published_at' => now(), 'settings' => ['isQuiz' => true]]);
    $this->linkOnly = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'Tes Wawancara',
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'listedForStudents' => false],
    ]);
});

test('students do not see a link-only form on their Beranda, but its link still opens it', function () {
    $this->actingAs($this->student)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentForms', 1)
            ->where('recentForms.0.id', $this->listed->id));

    $this->actingAs($this->student)->get(route('forms.public', $this->linkOnly->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('accessRestricted', false));
});

test('forms saved before the setting existed stay on the Beranda', function () {
    expect($this->listed->isListedForStudents())->toBeTrue()
        ->and($this->linkOnly->isListedForStudents())->toBeFalse();
});

test('a form for a cohort is listed for its students whatever the setting', function () {
    $cohort = Cohort::create(['name' => 'Kelas 7A', 'code' => 'KLS-7A', 'created_by' => $this->teacher->id]);
    $cohort->users()->attach($this->student->id);
    $this->linkOnly->cohorts()->attach($cohort->id);

    $this->actingAs($this->student)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('recentForms', 2));
});

test('the editor saves the setting, and the teacher sees which forms are link-only', function () {
    $this->actingAs($this->teacher)->patch(route('forms.update', $this->listed), [
        'title' => $this->listed->title,
        'slug' => $this->listed->slug,
        'questions' => $this->listed->questions,
        'settings' => ['isQuiz' => true, 'listedForStudents' => false],
        'published' => true,
    ])->assertSessionHasNoErrors();

    expect($this->listed->refresh()->isListedForStudents())->toBeFalse();

    $this->actingAs($this->teacher)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recentForms', fn ($forms) => collect($forms)->every(fn (array $form): bool => $form['linkOnly'] === true)));
});
