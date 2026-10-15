<?php

use App\Models\QuizForm;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 30, 'showResultsSummary' => true, 'resultsDetail' => 'full'],
        'questions' => [['id' => 1, 'title' => 'Hukum tajwid', 'type' => 'Multiple choice', 'options' => ['a', 'b'], 'answer' => 0, 'points' => 1]],
    ]);
    $this->student = User::factory()->create(['role' => 'siswa']);
});

/**
 * Open, start and hand in the exam as the exam page does.
 *
 * @param  array<int, string>  $answers
 */
function takeFormattedExam(QuizForm $exam, User $student, array $answers): void
{
    $token = test()->actingAs($student)->get(route('forms.public', $exam->slug))->inertiaProps('session.token');
    test()->actingAs($student)->postJson(route('forms.responses.start', $exam->slug), ['session_token' => $token])->assertOk();
    test()->actingAs($student)->postJson(route('forms.responses.store', $exam->slug), ['session_token' => $token, 'answers' => $answers])->assertOk();
}

test('formatted questions are saved as written, scored as before and shown as plain text where formatting has no place', function () {
    $title = 'Hukum <b>tajwid</b> pada lafaz <mark data-color="green"><u>ٱلرَّحْمَـٰنِ</u></mark> adalah?';
    $options = ['Alif Lam <i>Syamsiyah</i>', 'Alif Lam <mark data-color="yellow">Qamariyah</mark>'];

    // The editor saves the formatting with the question.
    $this->actingAs($this->teacher)->patch(route('forms.update', $this->exam), [
        'title' => $this->exam->title,
        'slug' => $this->exam->slug,
        'questions' => [[
            'id' => 1, 'title' => $title, 'description' => 'Baca <i>teliti</i>.', 'type' => 'Multiple choice',
            'options' => $options, 'answer' => 0, 'points' => 1, 'explanation' => 'Huruf <b>ra</b> termasuk syamsiyah.',
        ]],
        'settings' => $this->exam->settings,
        'published' => true,
    ])->assertSessionHasNoErrors();

    expect($this->exam->refresh()->questions[0])
        ->title->toBe($title)
        ->options->toBe($options);

    // A student's choice is the option as written, formatting included: it is right.
    takeFormattedExam($this->exam, $this->student, [1 => 'Alif Lam <i>Syamsiyah</i>']);
    expect($this->exam->responses()->sole()->score)->toBe(1);

    // Results: the question keeps its formatting (drawn by RichContent); the answers are text.
    $this->actingAs($this->student)->get(route('forms.results', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempts.0.score', 1)
            ->where('attempts.0.questions.0.title', $title)
            ->where('attempts.0.questions.0.answer', 'Alif Lam Syamsiyah')
            ->where('attempts.0.questions.0.correctAnswer', 'Alif Lam Syamsiyah')
            ->where('attempts.0.questions.0.explanation', 'Huruf <b>ra</b> termasuk syamsiyah.'));

    // The Excel workbook has the text without tags.
    $export = $this->actingAs($this->teacher)->get(route('forms.responses.export', $this->exam));
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet2.xml').(string) $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();

    expect($sheet)
        ->toContain('Hukum tajwid pada lafaz ٱلرَّحْمَـٰنِ adalah?')
        ->toContain('Alif Lam Syamsiyah')
        ->not->toContain('&lt;b&gt;')
        ->not->toContain('data-color');
});

test('the question bank and the dashboard card show formatted questions as plain text', function () {
    $this->exam->update(['questions' => [[
        'id' => 1, 'title' => 'Siapa <b>penemu</b> <mark data-color="pink">lampu</mark>?', 'type' => 'Multiple choice',
        'options' => ['<i>Edison</i>', 'Tesla'], 'answer' => 0, 'points' => 1,
    ]]]);

    $this->actingAs($this->teacher)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recentForms.0.firstQuestion.title', 'Siapa penemu lampu?')
            ->where('recentForms.0.firstQuestion.options.0', 'Edison'));
});
