<?php

use App\Http\Requests\SaveQuizDraftRequest;
use App\Models\QuizForm;
use App\Models\QuizSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->quizForm = QuizForm::factory()->create([
        'published_at' => now(),
        'settings' => ['collectEmail' => false, 'showProgress' => true, 'shuffleQuestions' => false, 'limitOneResponse' => true],
    ]);
    $this->student = User::factory()->create(['role' => 'siswa', 'nis' => '2024001']);
});

/**
 * Open the exam page and return the session token handed to the browser.
 */
function openExam(QuizForm $quizForm): string
{
    return test()->get(route('forms.public', $quizForm->slug))
        ->assertOk()
        ->inertiaProps('session.token');
}

test('draft answers saved on the server are restored after the server ip changes', function () {
    $this->actingAs($this->student);
    $token = openExam($this->quizForm);

    $this->postJson(route('forms.responses.draft', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Option 2'],
    ])->assertOk()->assertJson(['saved' => true]);

    // A new address means a new browser origin: no cookies, no localStorage. The student
    // logs in again and must get the answers back from the server.
    $this->flushSession();
    $this->actingAs($this->student)
        ->get(route('forms.public', $this->quizForm->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.token', $token)
            ->where('session.draft_answers', ['1' => 'Option 2'])
            ->whereType('session.draft_saved_at', 'string'));
});

test('drafts keep being saved when the login session expired mid exam', function () {
    $this->actingAs($this->student);
    $token = openExam($this->quizForm);

    auth()->logout();

    $this->postJson(route('forms.responses.draft', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Option 1'],
    ])->assertOk();

    expect(QuizSession::firstWhere('session_token', $token)->draft_answers)->toBe(['1' => 'Option 1']);
});

test('drafts cannot be written with an unknown token or into another students session', function () {
    $this->actingAs($this->student);
    $token = openExam($this->quizForm);

    $this->postJson(route('forms.responses.draft', $this->quizForm->slug), [
        'session_token' => 'not-a-real-token',
        'answers' => [1 => 'Option 1'],
    ])->assertNotFound();

    $this->actingAs(User::factory()->create(['role' => 'siswa', 'nis' => '2024002']))
        ->postJson(route('forms.responses.draft', $this->quizForm->slug), [
            'session_token' => $token,
            'answers' => [1 => 'Option 1'],
        ])->assertForbidden();

    expect(QuizSession::firstWhere('session_token', $token)->draft_answers)->toBeNull();
});

test('the server draft is cleared once the answers are submitted', function () {
    $this->actingAs($this->student);
    $token = openExam($this->quizForm);

    $this->postJson(route('forms.responses.draft', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Option 2'],
    ])->assertOk();

    $this->postJson(route('forms.responses.store', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Option 2'],
    ])->assertOk();

    $session = QuizSession::firstWhere('session_token', $token);

    expect($session->draft_answers)->toBeNull()
        ->and($session->submitted_at)->not->toBeNull();
});

test('students who still must change their password can save drafts', function () {
    $this->student->update(['must_change_password' => true]);
    $this->actingAs($this->student);
    $token = openExam($this->quizForm);

    $this->postJson(route('forms.responses.draft', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Option 1'],
    ])->assertOk();
});

test('on a shared lab computer another student never inherits the previous exam session', function () {
    $this->actingAs($this->student);
    $firstToken = openExam($this->quizForm);

    $this->postJson(route('forms.responses.draft', $this->quizForm->slug), [
        'session_token' => $firstToken,
        'answers' => [1 => 'Option 2'],
    ])->assertOk();

    // The browser still carries the respondent cookie of the first student.
    $this->flushSession();
    $nextStudent = User::factory()->create(['role' => 'siswa', 'nis' => '2024003']);

    $this->actingAs($nextStudent)
        ->withCookie('alsen_resp_id', 'user_'.$this->student->id)
        ->get(route('forms.public', $this->quizForm->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.token', fn (string $token) => $token !== $firstToken)
            ->where('session.draft_answers', null));

    auth()->logout();

    $this->withCookie('alsen_resp_id', 'user_'.$this->student->id)
        ->get(route('forms.public', $this->quizForm->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.token', fn (string $token) => $token !== $firstToken)
            ->where('session.draft_answers', null));
});

test('a late draft request after submission is rejected and never restored', function () {
    $this->actingAs($this->student);
    $token = openExam($this->quizForm);

    $this->postJson(route('forms.responses.store', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Option 2'],
    ])->assertOk();

    $this->postJson(route('forms.responses.draft', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Option 1'],
    ])->assertStatus(409)->assertJson(['saved' => false, 'submitted' => true]);

    expect(QuizSession::firstWhere('session_token', $token)->draft_answers)->toBeNull();
});

test('drafts only accept answers to questions of the quiz and of a bounded size', function () {
    $this->actingAs($this->student);
    $token = openExam($this->quizForm);

    $this->postJson(route('forms.responses.draft', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [999 => 'Bukan soal kuis ini'],
    ])->assertUnprocessable()->assertJsonValidationErrors('answers');

    $this->postJson(route('forms.responses.draft', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [1 => str_repeat('a', SaveQuizDraftRequest::MAX_PAYLOAD_BYTES + 1)],
    ])->assertUnprocessable()->assertJsonValidationErrors('answers');

    $this->postJson(route('forms.responses.draft', $this->quizForm->slug), [
        'session_token' => $token,
        'answers' => [1 => [[[['terlalu dalam']]]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('answers');

    expect(QuizSession::firstWhere('session_token', $token)->draft_answers)->toBeNull();
});
