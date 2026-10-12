<?php

use App\Actions\SubmitExpiredAttempts;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use App\Support\UnlockCode;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->exam = QuizForm::factory()->create([
        'published_at' => now(),
        'questions' => [
            ['id' => 1, 'title' => 'Soal 1', 'type' => 'Multiple choice', 'options' => ['Benar', 'Salah', 'Ragu', 'Lewat'], 'answer' => 0, 'required' => false, 'points' => 1],
            ['id' => 2, 'title' => 'Soal 2', 'type' => 'Short answer', 'answer' => 'Jakarta', 'required' => false, 'points' => 1],
        ],
        'settings' => ['collectEmail' => false, 'showProgress' => false, 'shuffleQuestions' => false, 'timeLimit' => 30, 'lockOnBlur' => true],
    ]);
});

test('answers to an exam that was not started on the exam page are refused', function () {
    $student = User::factory()->create(['role' => 'siswa']);

    // Opening the page alone (reading the rules) does not start the timer.
    $token = $this->actingAs($student)->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');

    $this->actingAs($student)->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Benar', 2 => 'Jakarta'],
    ])->assertForbidden()->assertJsonPath('message', 'Ujian belum dimulai dari halaman ujian. Muat ulang halaman, lalu tekan "Kerjakan Sekarang".');

    // Nor can a guest hand in without any attempt.
    auth()->logout();
    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'respondent_identifier' => 'anon_made_up_identifier',
        'answers' => [1 => 'Benar'],
    ])->assertForbidden();

    expect(QuizResponse::query()->count())->toBe(0);
});

test('a locked guest cannot slip past the lock by naming another attempt', function () {
    $attempt = startedAttempt($this->exam, attributes: ['respondent_identifier' => 'anon_locked_student', 'is_locked' => true]);
    $other = startedAttempt($this->exam, attributes: ['respondent_identifier' => 'anon_classmate']);

    // Their own browser (cookie) without the token: still their locked attempt.
    $this->withCredentials()->withCookie('alsen_resp_id', 'anon_locked_student')
        ->postJson(route('forms.responses.store', $this->exam->slug), [
            'respondent_identifier' => 'anon_classmate',
            'answers' => [1 => 'Benar'],
        ])->assertForbidden();

    // A made up identifier is not an attempt at all.
    $this->withCredentials()->withCookie('alsen_resp_id', 'anon_locked_student')
        ->postJson(route('forms.responses.store', $this->exam->slug), [
            'respondent_identifier' => 'anon_brand_new',
            'answers' => [1 => 'Benar'],
        ])->assertForbidden();

    expect(QuizResponse::query()->count())->toBe(0)
        ->and($other->refresh()->submitted_at)->toBeNull()
        ->and($attempt->refresh()->submitted_at)->toBeNull();
});

test('a handed in exam is not handed in again; opening the exam again starts a new attempt with its own timer', function () {
    $student = User::factory()->create(['role' => 'siswa']);
    $first = startedAttempt($this->exam, $student, ['started_at' => now()->subMinutes(10), 'expires_at' => now()->addMinutes(20)]);

    $this->actingAs($student)->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $first->session_token,
        'answers' => [1 => 'Benar'],
    ])->assertOk();

    // Sending again without the attempt's token (straight to the server) is refused.
    $this->actingAs($student)->postJson(route('forms.responses.store', $this->exam->slug), [
        'answers' => [1 => 'Salah'],
    ])->assertForbidden();

    $newToken = $this->actingAs($student)
        ->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('session.started_at', null)->has('quizForm.questions', 0))
        ->inertiaProps('session.token');

    expect($newToken)->not->toBe($first->session_token)
        ->and(QuizResponse::query()->count())->toBe(1)
        ->and(QuizSession::query()->where('user_id', $student->id)->count())->toBe(2);
});

test('answers sent long after the time from a page that stayed online count as they were saved when the time ran out', function () {
    $attempt = startedAttempt($this->exam, attributes: ['started_at' => now()->subMinutes(30), 'expires_at' => now()]);

    // The page was in touch until the end and saved the answers given in time...
    $this->postJson(route('forms.responses.draft', $this->exam->slug), ['session_token' => $attempt->session_token, 'answers' => [1 => 'Salah']])->assertJsonPath('saved', true);

    // ...then its timer was stopped and the student kept answering for ten more minutes.
    $this->travel(10)->minutes();
    $this->postJson(route('forms.responses.draft', $this->exam->slug), ['session_token' => $attempt->session_token, 'answers' => [1 => 'Benar', 2 => 'Jakarta']])
        ->assertStatus(409)
        ->assertJsonPath('expired', true);
    $this->getJson(route('forms.responses.status', ['quizForm' => $this->exam->slug, 'session_token' => $attempt->session_token]))->assertOk();

    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Benar', 2 => 'Jakarta'],
        'is_timeout' => true,
    ])->assertOk();

    expect(QuizResponse::query()->sole())
        ->answers->toEqual([1 => 'Salah'])
        ->score->toBe(0)
        ->late_seconds->toBeNull();
});

test('a page that was offline at the end hands in what it has, marked late for the teacher', function () {
    $attempt = startedAttempt($this->exam, attributes: ['started_at' => now()->subMinutes(30), 'expires_at' => now()->addMinutes(5)]);
    $this->postJson(route('forms.responses.draft', $this->exam->slug), ['session_token' => $attempt->session_token, 'answers' => [1 => 'Salah']]);

    // Wi-Fi dropped 5 minutes before the end; the answers stayed on the device and arrive later.
    $this->travel(15)->minutes();

    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Benar', 2 => 'Jakarta'],
        'is_timeout' => true,
    ])->assertOk();

    $response = QuizResponse::query()->sole();
    expect($response)->score->toBe(2)->and($response->late_seconds)->toBeGreaterThanOrEqual(600);

    $this->actingAs($this->exam->user)
        ->get(route('forms.edit', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.responses.gradebook.0.lateMinutes', 10));
});

test('a page that stayed online does not change what the server handed in for it', function () {
    $attempt = startedAttempt($this->exam, attributes: ['started_at' => now()->subMinutes(30), 'expires_at' => now()]);
    $this->postJson(route('forms.responses.draft', $this->exam->slug), ['session_token' => $attempt->session_token, 'answers' => [1 => 'Salah']]);

    $this->travel(3)->minutes();
    app(SubmitExpiredAttempts::class)->handle();

    $this->travel(5)->minutes();
    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Benar', 2 => 'Jakarta'],
        'is_timeout' => true,
    ])->assertOk();

    expect(QuizResponse::query()->sole())
        ->answers->toEqual([1 => 'Salah'])
        ->is_auto_submitted->toBeTrue();
});

test('answers too large for an exam are refused', function () {
    $attempt = startedAttempt($this->exam);

    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Benar', 2 => str_repeat('a', 300000)],
    ])->assertUnprocessable()->assertJsonValidationErrors('answers');
});

test('the unlock code nobody sees cannot be guessed', function () {
    expect(strlen(app(UnlockCode::class)->generate()))->toBe(40);
});
