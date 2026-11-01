<?php

use App\Models\QuizForm;
use App\Models\User;
use App\Services\Addons;
use App\Services\QuizRecap;
use App\Services\QuizResultsReport;
use App\Support\Addon;
use App\Support\QuizScoring;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Angket berskor: an interview sheet (like the PMB motivation questionnaire) where every answer
 * has its own score (A = 3, B = 2, C = 1) and the result is their sum. Quizzes with answer keys
 * are scored as before.
 */

/**
 * Two numbers of the PMB questionnaire, each a section with its sub-questions.
 *
 * @return list<array<string, mixed>>
 */
function interviewItems(): array
{
    return [
        ['id' => 10, 'title' => '1. Alasan memilih sekolah', 'type' => 'Section'],
        ['id' => 1, 'title' => 'Anda memilih sekolah ini karena…', 'type' => 'Multiple choice', 'required' => true,
            'options' => ['Kemauan sendiri saja', 'Kemauan sendiri dan didukung orang tua', 'Kemauan orang tua saja'], 'optionScores' => [3, 2, 1], 'points' => 1],
        ['id' => 2, 'title' => 'Sekolah ini bagi Anda merupakan…', 'type' => 'Drop-down',
            'options' => ['Pilihan satu-satunya', 'Pilihan pertama', 'Pilihan cadangan'], 'optionScores' => [3, 2, 1]],
        ['id' => 20, 'title' => '2. Kesiapan belajar', 'type' => 'Section'],
        ['id' => 3, 'title' => 'Yang sudah Anda siapkan', 'type' => 'Checkboxes',
            'options' => ['Hafalan juz 30', 'Seragam', 'Belum ada'], 'optionScores' => [2, 1, 0]],
        ['id' => 4, 'title' => 'Seberapa sering Anda…', 'type' => 'Multiple-choice grid',
            'rows' => ['Membaca Al-Qur\'an', 'Belajar di rumah'], 'columns' => ['Selalu', 'Kadang', 'Tidak pernah'], 'columnScores' => [3, 2, 1]],
    ];
}

/**
 * Open, start and hand in the questionnaire as the exam page does.
 *
 * @param  array<int, mixed>  $answers
 * @return list<array<string, mixed>> The questions the respondent received.
 */
function answerInterview(QuizForm $questionnaire, User $respondent, array $answers): array
{
    $token = test()->actingAs($respondent)->get(route('forms.public', $questionnaire->slug))->inertiaProps('session.token');
    $questions = test()->actingAs($respondent)->postJson(route('forms.responses.start', $questionnaire->slug), ['session_token' => $token])
        ->assertOk()
        ->json('questions');
    test()->actingAs($respondent)->postJson(route('forms.responses.store', $questionnaire->slug), ['session_token' => $token, 'answers' => $answers])->assertOk();

    return $questions;
}

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->questionnaire = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'Angket Motivasi PMB',
        'published_at' => now(),
        'questions' => interviewItems(),
        'settings' => ['isQuiz' => true, 'optionScoring' => true, 'showResultsSummary' => true, 'resultsDetail' => 'full'],
    ]);
    $this->scoring = new QuizScoring;
});

test('every answer earns the score of the choice made, and the maximum is the best choice of each question', function () {
    $questions = interviewItems();

    expect($this->scoring->earned($questions[1], 'Kemauan sendiri saja'))->toBe(3)
        ->and($this->scoring->earned($questions[1], 'Kemauan orang tua saja'))->toBe(1)
        ->and($this->scoring->earned($questions[2], 'Pilihan pertama'))->toBe(2)
        // Ticked options add up; the grid earns the column chosen in every row.
        ->and($this->scoring->earned($questions[4], ['Hafalan juz 30', 'Seragam']))->toBe(3)
        ->and($this->scoring->earned($questions[5], [0 => 0, 1 => 2]))->toBe(4)
        // An answer that is no option earns nothing.
        ->and($this->scoring->earned($questions[1], 'Lainnya: karena dekat'))->toBe(0)
        ->and($this->scoring->points($questions[1]))->toBe(3)
        ->and($this->scoring->points($questions[4]))->toBe(3)
        ->and($this->scoring->points($questions[5]))->toBe(6)
        ->and($this->scoring->maxPoints($questions))->toBe(15);

    // No right or wrong answer: left out of everything built on answer keys.
    expect($this->scoring->isAutoScored($questions[1]))->toBeFalse()
        ->and($this->scoring->isCorrect($questions[1], 'Kemauan sendiri saja'))->toBeNull();

    $result = $this->scoring->result($questions, [1 => 'Kemauan sendiri saja', 2 => 'Pilihan cadangan', 3 => ['Seragam'], 4 => [0 => 0, 1 => 1]]);
    expect($result)->toMatchArray(['score' => 10, 'max' => 15]);
});

test('the scores of the choices win over an answer key left from before', function () {
    $question = [...interviewItems()[1], 'answer' => 2];

    expect($this->scoring->isAutoScored($question))->toBeFalse()
        ->and($this->scoring->earned($question, 'Kemauan sendiri saja'))->toBe(3);
});

test('a quiz with answer keys is scored exactly as before', function () {
    $questions = [
        ['id' => 1, 'title' => 'Ibu kota?', 'type' => 'Multiple choice', 'options' => ['Bandung', 'Jakarta'], 'answer' => 1, 'points' => 10],
        ['id' => 2, 'title' => '7 × 8', 'type' => 'Short answer', 'answer' => '56', 'points' => 5],
    ];

    expect($this->scoring->score($questions, [1 => 'Jakarta', 2 => '56']))->toBe(15)
        ->and($this->scoring->score($questions, [1 => 'Bandung', 2 => '56']))->toBe(5)
        ->and($this->scoring->maxPoints($questions))->toBe(15)
        ->and($this->scoring->isOptionScored($questions[0]))->toBeFalse();
});

test('each section gets the subtotal of its questions', function () {
    $sections = $this->scoring->sectionScores(interviewItems(), [1 => 'Kemauan sendiri saja', 2 => 'Pilihan pertama', 3 => ['Hafalan juz 30'], 4 => [0 => 0, 1 => 0]]);

    expect($sections)->toBe([
        ['index' => 0, 'title' => '1. Alasan memilih sekolah', 'score' => 5, 'max' => 6],
        ['index' => 1, 'title' => '2. Kesiapan belajar', 'score' => 8, 'max' => 9],
    ]);
});

test('the editor saves the scores of a scored questionnaire, and drops them when it is switched off', function () {
    $payload = fn (bool $optionScoring): array => [
        'title' => $this->questionnaire->title,
        'slug' => $this->questionnaire->slug,
        'questions' => interviewItems(),
        'settings' => ['isQuiz' => true, 'optionScoring' => $optionScoring],
        'published' => true,
    ];

    $this->actingAs($this->teacher)->patch(route('forms.update', $this->questionnaire), $payload(true))->assertSessionHasNoErrors();
    expect($this->questionnaire->refresh()->questions[1]['optionScores'])->toBe([3, 2, 1])
        ->and($this->questionnaire->questions[5]['columnScores'])->toBe([3, 2, 1])
        ->and($this->questionnaire->isScoredQuestionnaire())->toBeTrue();

    $this->actingAs($this->teacher)->patch(route('forms.update', $this->questionnaire), $payload(false))->assertSessionHasNoErrors();
    expect($this->questionnaire->refresh()->questions[1])->not->toHaveKey('optionScores')
        ->and($this->questionnaire->questions[5])->not->toHaveKey('columnScores')
        ->and($this->questionnaire->isScoredQuestionnaire())->toBeFalse();
});

test('a score must be a whole number from 0 to 100', function () {
    $items = interviewItems();
    $items[1]['optionScores'] = [3, 101, -1];

    $this->actingAs($this->teacher)->patch(route('forms.update', $this->questionnaire), [
        'title' => $this->questionnaire->title,
        'slug' => $this->questionnaire->slug,
        'questions' => $items,
        'settings' => ['isQuiz' => true, 'optionScoring' => true],
    ])->assertSessionHasErrors(['questions.1.optionScores.1', 'questions.1.optionScores.2']);
});

test('respondents never receive the scores, and their result is the total with a subtotal per section', function () {
    $student = User::factory()->create(['role' => 'siswa']);

    $received = answerInterview($this->questionnaire, $student, [
        1 => 'Kemauan sendiri saja', 2 => 'Pilihan cadangan', 3 => ['Hafalan juz 30', 'Seragam'], 4 => [0 => 1, 1 => 2],
    ]);

    foreach ($received as $question) {
        expect($question)->not->toHaveKey('optionScores')->not->toHaveKey('columnScores');
    }
    expect($this->questionnaire->responses()->sole()->score)->toBe(10);

    $this->actingAs($student)->get(route('forms.results', $this->questionnaire->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('isScoredQuestionnaire', true)
            ->where('attempts.0.score', 10)
            ->where('attempts.0.max', 15)
            ->where('attempts.0.sections.0.score', 4)
            ->where('attempts.0.sections.1.score', 6)
            ->where('attempts.0.questions.0.isCorrect', null)
            ->where('attempts.0.questions.0.earned', 3)
            ->where('attempts.0.questions.0.points', 3)
            ->where('attempts.0.questions.0.correctAnswer', null));
});

test('the add-ons built on right and wrong answers, and the quiz recap, leave a scored questionnaire out', function () {
    foreach ([Addon::ItemAnalysis, Addon::Remedial, Addon::Misconception, Addon::QuizRecap] as $addon) {
        app(Addons::class)->install($addon, $this->teacher);
    }
    $quiz = QuizForm::factory()->for($this->teacher)->create(['published_at' => now(), 'settings' => ['isQuiz' => true]]);

    expect($this->questionnaire->usesAddon(Addon::ItemAnalysis))->toBeFalse()
        ->and($this->questionnaire->usesAddon(Addon::Remedial))->toBeFalse()
        ->and($this->questionnaire->usesAddon(Addon::Misconception))->toBeFalse()
        ->and($quiz->usesAddon(Addon::ItemAnalysis))->toBeTrue();

    $recapQuizzes = collect(app(QuizRecap::class)->build($this->teacher)['quizzes'])->pluck('id');
    expect($recapQuizzes)->toContain($quiz->id)->not->toContain($this->questionnaire->id);
});

test('the Excel report has the score of each answer and a sheet with the subtotal per section', function () {
    answerInterview($this->questionnaire, User::factory()->create(['role' => 'siswa', 'name' => 'Calon Murid']), [
        1 => 'Kemauan orang tua saja', 2 => 'Pilihan satu-satunya', 3 => ['Seragam'], 4 => [0 => 0, 1 => 0],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'questionnaire-report-');

    app(QuizResultsReport::class)->save($this->questionnaire->refresh(), $path);

    $zip = new ZipArchive;
    $zip->open($path);
    $workbook = (string) $zip->getFromName('xl/workbook.xml');
    $recap = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $answers = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
    $sections = (string) $zip->getFromName('xl/worksheets/sheet3.xml');
    $zip->close();
    @unlink($path);

    expect($workbook)->toContain('Skor per Bagian')
        // The same names as on the results page: a total score and its share, no pass or fail.
        ->and($recap)->toContain('Total Skor')->toContain('Persentase')->not->toContain('Belum Tuntas')
        ->and($answers)->toContain('Skor: 1')
        ->and($answers)->toContain('Kemauan sendiri saja = 3')
        ->and($sections)->toContain('1. Alasan memilih sekolah (maks. 6)')
        ->and($sections)->toContain('Total Skor (maks. 15)')
        ->and($sections)->toContain('<v>4</v>')
        ->and($sections)->toContain('<v>7</v>')
        ->and($sections)->toContain('<v>11</v>');
});
