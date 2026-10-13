<?php

use App\Actions\SubmitExpiredAttempts;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-10-13 08:00:00');
    $this->exam = QuizForm::factory()->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 30, 'lockOnBlur' => true],
        'questions' => [
            ['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4'], 'answer' => '4', 'points' => 1],
            ['id' => 2, 'title' => '3 + 3 = ?', 'type' => 'Multiple choice', 'options' => ['6', '7'], 'answer' => '6', 'points' => 1],
            ['id' => 3, 'title' => '5 + 5 = ?', 'type' => 'Short answer', 'answer' => '10', 'points' => 1],
        ],
    ]);
    $this->student = User::factory()->create(['role' => 'siswa']);
    $this->actingAs($this->student);
    $this->token = $this->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $this->token])->assertOk();
});

test('the questions marked ragu-ragu are kept with the draft and come back on another device', function () {
    $this->postJson(route('forms.responses.draft', $this->exam->slug), [
        'session_token' => $this->token,
        'answers' => [1 => '4'],
        // Unknown questions and repeats are left out.
        'doubts' => ['2', 2, '99', ['nested']],
    ])->assertOk();

    expect(QuizSession::sole()->doubtful_question_ids)->toBe(['2']);

    $this->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('session.doubtful_question_ids', ['2']));

    // A draft from an older page without the marks keeps them.
    $this->postJson(route('forms.responses.draft', $this->exam->slug), ['session_token' => $this->token, 'answers' => [1 => '4', 2 => '6']])->assertOk();
    expect(QuizSession::sole()->doubtful_question_ids)->toBe(['2']);

    // Unmarked again.
    $this->postJson(route('forms.responses.draft', $this->exam->slug), ['session_token' => $this->token, 'answers' => [1 => '4'], 'doubts' => []])->assertOk();
    expect(QuizSession::sole()->doubtful_question_ids)->toBe([]);
});

test('the marks are handed in with the answers', function () {
    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $this->token,
        'answers' => [1 => '4', 2 => '7', 3 => '10'],
        'doubts' => ['2', '3'],
    ])->assertSuccessful();

    expect(QuizResponse::sole()->doubtful_question_ids)->toBe(['2', '3']);
});

test('a page sending no marks hands in those of the draft, and none on the next attempt', function () {
    $this->postJson(route('forms.responses.draft', $this->exam->slug), ['session_token' => $this->token, 'answers' => [1 => '4'], 'doubts' => ['1']])->assertOk();

    $this->postJson(route('forms.responses.store', $this->exam->slug), ['session_token' => $this->token, 'answers' => [1 => '4']])->assertSuccessful();

    expect(QuizResponse::sole()->doubtful_question_ids)->toBe(['1']);
});

test('a locked attempt hands in the marks it had when it was locked', function () {
    $this->postJson(route('forms.responses.draft', $this->exam->slug), ['session_token' => $this->token, 'answers' => [1 => '3'], 'doubts' => ['1']])->assertOk();
    $this->postJson(route('forms.responses.lock', $this->exam->slug), ['session_token' => $this->token])->assertOk();

    // Behind the lock screen the student changes everything.
    Carbon::setTestNow('2026-10-13 08:31:30');
    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $this->token,
        'answers' => [1 => '4'],
        'doubts' => [],
        'is_timeout' => true,
    ])->assertSuccessful();

    expect(QuizResponse::sole())
        ->answers->toBe([1 => '3'])
        ->doubtful_question_ids->toBe(['1']);
});

test('an attempt handed in by the server when the time ran out keeps its marks', function () {
    $this->postJson(route('forms.responses.draft', $this->exam->slug), ['session_token' => $this->token, 'answers' => [1 => '4'], 'doubts' => ['3']])->assertOk();

    Carbon::setTestNow('2026-10-13 08:35:00');
    expect(app(SubmitExpiredAttempts::class)->handle())->toBe(1);

    expect(QuizResponse::sole())
        ->is_auto_submitted->toBeTrue()
        ->doubtful_question_ids->toBe(['3']);
});
