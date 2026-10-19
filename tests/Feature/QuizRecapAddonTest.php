<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\Addons;
use App\Support\Addon;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Rekap Kuis add-on: a teacher's grades of all their quizzes in one table, a row per student and
 * a column per quiz, by class, and as an Excel workbook. Only there while installed.
 */
beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru', 'name' => 'Bu Rina']);
    $questions = [
        ['id' => 1, 'title' => 'Ibu kota Indonesia?', 'type' => 'Short answer', 'answer' => 'Jakarta', 'points' => 1, 'required' => true],
        ['id' => 2, 'title' => '2 + 2?', 'type' => 'Short answer', 'answer' => '4', 'points' => 1, 'required' => true],
    ];
    $quiz = fn (string $title, string $createdAt, array $settings = ['isQuiz' => true]) => QuizForm::factory()->for($this->teacher)->create([
        'title' => $title, 'questions' => $questions, 'settings' => $settings, 'published_at' => now(), 'created_at' => Carbon::parse($createdAt),
    ]);
    $this->ipa = $quiz('Kuis IPA', '2026-09-01');
    $this->mtk = $quiz('Kuis Matematika', '2026-08-01');
    $this->survey = $quiz('Survei Kantin', '2026-07-01', ['isQuiz' => false]);

    $this->ani = User::factory()->create(['role' => 'siswa', 'name' => 'Ani', 'nis' => '1001', 'kelas' => '7A']);
    $this->budi = User::factory()->create(['role' => 'siswa', 'name' => 'Budi', 'nis' => '1002', 'kelas' => '7B']);
    $this->citra = User::factory()->create(['role' => 'siswa', 'name' => 'Citra', 'nis' => '1003', 'kelas' => '7A']);

    $answer = fn (QuizForm $form, ?User $student, array $answers, array $guest = []) => QuizResponse::factory()->create([
        'quiz_form_id' => $form->id, 'user_id' => $student?->id, 'answers' => $answers, ...$guest,
    ]);
    // Ani took Matematika twice: the better attempt counts.
    $answer($this->mtk, $this->ani, [1 => 'Bandung', 2 => '4']);
    $answer($this->mtk, $this->ani, [1 => 'Jakarta', 2 => '4']);
    $answer($this->ipa, $this->ani, [1 => 'Jakarta', 2 => '5']);
    $answer($this->ipa, $this->budi, [1 => 'Surabaya', 2 => '5']);
    $answer($this->ipa, null, [1 => 'Jakarta', 2 => '4'], ['respondent_identifier' => 'anon_tamu', 'respondent_name' => 'Tamu Dewi', 'respondent_class' => '7B']);
    $answer($this->survey, $this->budi, [1 => 'Enak']);

    // Another teacher's quiz is not in this recap.
    QuizForm::factory()->create(['title' => 'Kuis Guru Lain', 'published_at' => now()]);
});

test('without the add-on there is no recap, no tile and no download', function () {
    $this->actingAs($this->teacher)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('quizRecapEnabled', false));

    $this->get(route('quiz-recap.index'))->assertNotFound();
    $this->get(route('quiz-recap.export'))->assertNotFound();
});

test('installed, the apps menu has Rekap Kuis and the page lists every quiz with the best grades', function () {
    app(Addons::class)->install(Addon::QuizRecap, $this->teacher);

    $this->actingAs($this->teacher)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('quizRecapEnabled', true));

    $this->get(route('quiz-recap.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('QuizRecap/Index')
            // Own graded quizzes only, oldest first; the survey and the other teacher's quiz are not columns.
            ->where('quizzes', [['id' => $this->mtk->id, 'title' => 'Kuis Matematika'], ['id' => $this->ipa->id, 'title' => 'Kuis IPA']])
            ->where('classes', ['7A', '7B'])
            ->where('kelas', null)
            // Students who handed in a quiz, by class then name; Citra handed nothing in.
            ->has('students', 3)
            ->where('students.0.name', 'Ani')
            ->where('students.0.grades', [['grade' => 100, 'provisional' => false], ['grade' => 50, 'provisional' => false]])
            ->where('students.1.name', 'Budi')
            ->where('students.1.grades', [null, ['grade' => 0, 'provisional' => false]])
            ->where('students.2.name', 'Tamu Dewi')
            ->where('students.2.kelas', '7B')
            ->where('students.2.grades', [null, ['grade' => 100, 'provisional' => false]])
            ->where('exportUrl', route('quiz-recap.export')));
});

test('a class shows all its students, also the ones who still owe a quiz', function () {
    app(Addons::class)->install(Addon::QuizRecap, $this->teacher);

    $this->actingAs($this->teacher)->get(route('quiz-recap.index', ['kelas' => '7A']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kelas', '7A')
            ->has('students', 2)
            ->where('students.0.name', 'Ani')
            ->where('students.1.name', 'Citra')
            ->where('students.1.grades', [null, null])
            ->where('exportUrl', route('quiz-recap.export', ['kelas' => '7A'])));

    // An unknown class is no filter.
    $this->get(route('quiz-recap.index', ['kelas' => '9Z']))
        ->assertInertia(fn (Assert $page) => $page->where('kelas', null)->has('students', 3));
});

test('the recap downloads as an Excel workbook with a column per quiz', function () {
    app(Addons::class)->install(Addon::QuizRecap, $this->teacher);
    Carbon::setTestNow('2026-10-19 10:00:00');

    $response = $this->actingAs($this->teacher)->get(route('quiz-recap.export', ['kelas' => '7A']))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('rekap-kuis-7a-2026-10-19.xlsx');

    $zip = new ZipArchive;
    expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $strings = $zip->getFromName('xl/sharedStrings.xml') ?: '';
    $zip->close();

    $text = $sheet.$strings;
    expect($text)->toContain('Rekap Kuis')
        ->toContain('Kelas 7A')
        ->toContain('Kuis Matematika')
        ->toContain('Kuis IPA')
        ->toContain('Ani')
        ->toContain('Citra')
        ->not->toContain('Budi')
        ->not->toContain('Survei Kantin');

    Carbon::setTestNow();
});

test('uninstalled, the tile and the page are gone again; students never get them', function () {
    app(Addons::class)->install(Addon::QuizRecap, $this->teacher);
    app(Addons::class)->uninstall(Addon::QuizRecap, $this->teacher);

    $this->actingAs($this->teacher)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('quizRecapEnabled', false));
    $this->get(route('quiz-recap.index'))->assertNotFound();

    $this->actingAs($this->ani)->get(route('quiz-recap.index'))->assertForbidden();
});

test('installed by an admin for everyone, every teacher has their own recap', function () {
    $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
    app(Addons::class)->install(Addon::QuizRecap, $admin, forEveryone: true);
    $otherTeacher = User::factory()->create(['role' => 'guru']);

    $this->actingAs($otherTeacher)->get(route('quiz-recap.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('quizzes', [])->where('students', []));
});

test('the add-on is not switched per exam', function () {
    expect(Addon::QuizRecap->perExam())->toBeFalse()
        ->and(Addon::ItemAnalysis->perExam())->toBeTrue();

    $catalog = collect(app(Addons::class)->catalog($this->teacher))->keyBy('key');
    expect($catalog['quiz-recap'])->toMatchArray(['name' => 'Rekap Kuis', 'perExam' => false, 'installed' => false]);
});
