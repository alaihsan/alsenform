<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->quizForm = QuizForm::factory()->create([
        'title' => 'Ujian Tengah Semester Matematika',
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 45, 'lockOnBlur' => true],
        'questions' => [
            ['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4'], 'answer' => '4', 'required' => true, 'points' => 10],
            ['id' => 2, 'title' => 'Ibu kota Indonesia?', 'type' => 'Short answer', 'answer' => 'Jakarta', 'required' => true, 'points' => 15],
            ['id' => 3, 'title' => 'Ceritakan liburanmu', 'type' => 'Paragraph', 'required' => false, 'points' => 5],
        ],
    ]);
    $this->student = User::factory()->create(['role' => 'siswa', 'nis' => '2024101']);
});

/**
 * Open the exam page as the current user and return the session token handed to the browser.
 */
function openExamPage(QuizForm $quizForm): string
{
    return test()->get(route('forms.public', $quizForm->slug))
        ->assertOk()
        ->inertiaProps('session.token');
}

test('opening an exam shows its summary but neither starts the timer nor sends the questions', function () {
    $this->actingAs($this->student)
        ->get(route('forms.public', $this->quizForm->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PublicQuiz')
            ->where('quizForm.title', 'Ujian Tengah Semester Matematika')
            ->has('quizForm.questions', 0)
            ->where('quizForm.startUrl', route('forms.responses.start', $this->quizForm->slug))
            ->where('examSummary.questionCount', 3)
            ->where('examSummary.requiredCount', 2)
            ->where('examSummary.totalPoints', 30)
            ->where('examSummary.timeLimitMinutes', 45)
            ->where('session.started_at', null)
            ->where('session.expires_at', null)
        );

    expect(QuizSession::sole()->started_at)->toBeNull();
});

test('pressing kerjakan sekarang starts the timer and hands out the questions without answer keys', function () {
    Carbon::setTestNow('2026-10-04 08:00:00');
    $this->actingAs($this->student);
    $token = openExamPage($this->quizForm);

    Carbon::setTestNow('2026-10-04 08:03:00');
    $this->postJson(route('forms.responses.start', $this->quizForm->slug), ['session_token' => $token])
        ->assertOk()
        ->assertJsonCount(3, 'questions')
        ->assertJsonMissingPath('questions.0.answer')
        ->assertJsonMissingPath('questions.1.answer')
        ->assertJsonPath('session.started_at', '2026-10-04T08:03:00.000000Z')
        ->assertJsonPath('session.expires_at', '2026-10-04T08:48:00.000000Z');

    // Reloading the page resumes the running exam directly, with the same deadline.
    $this->get(route('forms.public', $this->quizForm->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->has('quizForm.questions', 3)
            ->where('session.expires_at', '2026-10-04T08:48:00.000000Z')
        );

    Carbon::setTestNow();
});

test('a repeated start request keeps the original start time', function () {
    Carbon::setTestNow('2026-10-04 08:00:00');
    $this->actingAs($this->student);
    $token = openExamPage($this->quizForm);

    $this->postJson(route('forms.responses.start', $this->quizForm->slug), ['session_token' => $token])->assertOk();

    Carbon::setTestNow('2026-10-04 08:10:00');
    $this->postJson(route('forms.responses.start', $this->quizForm->slug), ['session_token' => $token])
        ->assertOk()
        ->assertJsonPath('session.started_at', '2026-10-04T08:00:00.000000Z')
        ->assertJsonPath('session.expires_at', '2026-10-04T08:45:00.000000Z');

    Carbon::setTestNow();
});

test('another student cannot start someone else\'s exam session', function () {
    $this->actingAs($this->student);
    $token = openExamPage($this->quizForm);

    $this->actingAs(User::factory()->create(['role' => 'siswa', 'nis' => '2024102']))
        ->postJson(route('forms.responses.start', $this->quizForm->slug), ['session_token' => $token])
        ->assertForbidden();

    $this->postJson(route('forms.responses.start', $this->quizForm->slug), ['session_token' => 'not-a-real-token'])
        ->assertNotFound();

    expect(QuizSession::firstWhere('session_token', $token)->started_at)->toBeNull();
});

test('an exam that was already submitted cannot be started again', function () {
    $this->actingAs($this->student);
    $token = openExamPage($this->quizForm);
    QuizSession::firstWhere('session_token', $token)->update(['started_at' => now(), 'submitted_at' => now()]);

    $this->postJson(route('forms.responses.start', $this->quizForm->slug), ['session_token' => $token])
        ->assertStatus(409)
        ->assertJsonPath('submitted', true);
});

test('students who still have to change their password can start an exam', function () {
    $this->student->update(['must_change_password' => true]);
    $this->actingAs($this->student);
    $token = openExamPage($this->quizForm);

    $this->postJson(route('forms.responses.start', $this->quizForm->slug), ['session_token' => $token])
        ->assertOk()
        ->assertJsonCount(3, 'questions');
});

test('a started exam can be submitted as before', function () {
    $this->actingAs($this->student);
    $token = openExamPage($this->quizForm);
    $this->postJson(route('forms.responses.start', $this->quizForm->slug), ['session_token' => $token])->assertOk();

    $this->postJson(route('forms.responses.store', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [1 => '4', 2 => 'Jakarta'],
    ])->assertOk();

    expect(QuizResponse::sole()->score)->toBe(25);
});

test('a plain survey without a time limit opens directly', function () {
    $survey = QuizForm::factory()->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => false],
    ]);

    $this->get(route('forms.public', $survey->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('quizForm.questions', 1)
            ->where('session.started_at', fn (?string $startedAt) => $startedAt !== null)
            ->where('examSummary.timeLimitMinutes', null)
        );
});

test('an exam still opens when the database was not migrated after an update', function () {
    // Simulate a server where "php artisan migrate" was skipped: started_at is still required.
    Schema::table('quiz_sessions', function (Blueprint $table) {
        $table->timestamp('started_at')->nullable(false)->change();
    });

    $this->actingAs($this->student)
        ->get(route('forms.public', $this->quizForm->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('quizForm.questions', 3)
            ->where('session.started_at', fn (?string $startedAt) => $startedAt !== null)
            ->where('session.expires_at', fn (?string $expiresAt) => $expiresAt !== null)
        );

    expect(QuizSession::sole()->started_at)->not->toBeNull();
});
