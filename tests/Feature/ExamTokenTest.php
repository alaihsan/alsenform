<?php

use App\Models\QuizForm;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\Addons;
use App\Support\Addon;
use App\Support\ExamToken;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->student = User::factory()->create(['role' => 'siswa']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 60, 'addons' => ['exam-token' => true]],
        'questions' => [['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4'], 'answer' => '4', 'points' => 1]],
    ]);
    app(Addons::class)->install(Addon::ExamToken, $this->teacher);
    Carbon::setTestNow('2026-10-13 08:20:00');
});

/**
 * Open the exam page as the signed-in user and return the session token handed to the browser.
 */
function openTokenExam(QuizForm $exam): string
{
    return test()->get(route('forms.public', $exam->slug))->assertOk()->inertiaProps('session.token');
}

test('the token is six letters, the same for 15 minutes, then another, and different for every exam', function () {
    $tokens = app(ExamToken::class);
    $other = QuizForm::factory()->for($this->teacher)->create();

    $token = $tokens->current($this->exam, Carbon::parse('2026-10-13 08:15:00'));

    expect($token)->toMatch('/^[A-HJ-NP-Z]{6}$/')
        ->and($tokens->current($this->exam, Carbon::parse('2026-10-13 08:29:59')))->toBe($token)
        ->and($tokens->current($this->exam, Carbon::parse('2026-10-13 08:30:00')))->not->toBe($token)
        ->and($tokens->current($other, Carbon::parse('2026-10-13 08:15:00')))->not->toBe($token)
        ->and($tokens->changesAt(Carbon::parse('2026-10-13 08:20:00'))->toDateTimeString())->toBe('2026-10-13 08:30:00');
});

test('a student starts only with the token shown by the proctor', function () {
    $this->actingAs($this->student);
    $session = openTokenExam($this->exam);

    $this->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.requiresExamToken', true));

    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session])
        ->assertUnprocessable()
        ->assertJsonPath('errors.exam_token.0', 'Masukkan token dari pengawas.');

    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session, 'exam_token' => 'ABCDEF'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.exam_token.0', 'Token salah atau sudah berganti. Minta token terbaru ke pengawas.');

    expect(QuizSession::sole()->started_at)->toBeNull();

    // Typed in lower case with a space, as students do.
    $typed = strtolower(substr(app(ExamToken::class)->current($this->exam), 0, 3).' '.substr(app(ExamToken::class)->current($this->exam), 3));

    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session, 'exam_token' => $typed])
        ->assertOk()
        ->assertJsonCount(1, 'questions');

    expect(QuizSession::sole()->started_at)->not->toBeNull();

    // A reload, or a retry of the start on weak Wi-Fi, needs no token again.
    Carbon::setTestNow('2026-10-13 09:10:00');
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session])->assertOk();
});

test('the token just replaced is still accepted for five minutes', function () {
    $previous = app(ExamToken::class)->current($this->exam, Carbon::parse('2026-10-13 08:14:00'));
    $this->actingAs($this->student);
    $session = openTokenExam($this->exam);

    Carbon::setTestNow('2026-10-13 08:20:01');
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session, 'exam_token' => $previous])
        ->assertUnprocessable();

    Carbon::setTestNow('2026-10-13 08:19:59');
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session, 'exam_token' => $previous])
        ->assertOk();
});

test('wrong tokens are limited, so the token cannot be guessed', function () {
    $this->actingAs($this->student);
    $session = openTokenExam($this->exam);

    foreach (range(1, ExamToken::MAX_WRONG_PER_MINUTE) as $attempt) {
        $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session, 'exam_token' => 'ZZZZZZ']);
    }

    // Even the right token waits until the minute is over.
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session, 'exam_token' => app(ExamToken::class)->current($this->exam)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.exam_token.0', fn (string $message) => str_starts_with($message, 'Terlalu banyak token salah.'));

    expect(QuizSession::sole()->started_at)->toBeNull();
});

test('without the add-on switched on for the exam, no token is asked', function () {
    $this->exam->update(['settings' => ['isQuiz' => true, 'timeLimit' => 60]]);
    app()->forgetScopedInstances();
    $this->actingAs($this->student);
    $session = openTokenExam($this->exam);

    $this->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.requiresExamToken', false));
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session])->assertOk();
});

test('the exam\'s teacher tries the exam without the token', function () {
    $this->actingAs($this->teacher);
    $session = openTokenExam($this->exam);

    $this->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.requiresExamToken', false));
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session])->assertOk();
});

test('a survey with the token switched on waits for the token too, and cannot be answered around it', function () {
    $this->exam->update(['settings' => ['isQuiz' => false, 'addons' => ['exam-token' => true]]]);
    app()->forgetScopedInstances();
    $this->actingAs($this->student);
    $session = openTokenExam($this->exam);

    expect(QuizSession::sole()->started_at)->toBeNull();

    $this->postJson(route('forms.responses.store', $this->exam->slug), ['session_token' => $session, 'answers' => [1 => '4']])
        ->assertForbidden();
});

test('the proctor sees the current token in the editor, students do not', function () {
    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.examTokenUrl', route('forms.exam-token', $this->exam)));

    $this->actingAs($this->teacher)->getJson(route('forms.exam-token', $this->exam))
        ->assertOk()
        ->assertJsonPath('token', app(ExamToken::class)->current($this->exam))
        ->assertJsonPath('changesAt', '2026-10-13T08:30:00.000000Z')
        ->assertJsonPath('previousToken', null)
        ->assertJsonPath('rotateMinutes', 15)
        ->assertJsonPath('active', true);

    Carbon::setTestNow('2026-10-13 08:31:00');
    $this->actingAs($this->teacher)->getJson(route('forms.exam-token', $this->exam))
        ->assertJsonPath('previousToken', app(ExamToken::class)->current($this->exam, Carbon::parse('2026-10-13 08:20:00')))
        ->assertJsonPath('previousAcceptedUntil', '2026-10-13T08:35:00.000000Z');

    $this->actingAs($this->student)->getJson(route('forms.exam-token', $this->exam))->assertForbidden();

    app(Addons::class)->uninstall(Addon::ExamToken, $this->teacher);
    app()->forgetScopedInstances();
    $this->actingAs($this->teacher)->getJson(route('forms.exam-token', $this->exam))->assertForbidden();
});
