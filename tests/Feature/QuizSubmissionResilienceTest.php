<?php

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $settings
 */
function publishedQuiz(array $settings = []): QuizForm
{
    return QuizForm::factory()->create([
        'published_at' => now(),
        'settings' => array_merge(['collectEmail' => false, 'showProgress' => true, 'shuffleQuestions' => false], $settings),
    ]);
}

function examSessionFor(QuizForm $quizForm, string $respondentIdentifier): QuizSession
{
    return QuizSession::create([
        'quiz_form_id' => $quizForm->id,
        'respondent_identifier' => $respondentIdentifier,
        'session_token' => Str::random(40),
        'started_at' => now(),
        'is_locked' => false,
    ]);
}

test('a whole class behind one ip address (expose / nat) can submit at the same time', function () {
    $quizForm = publishedQuiz();

    foreach (range(1, 30) as $student) {
        // Every browser carries the encrypted respondent cookie issued when the quiz page was opened.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withCredentials()
            ->withCookie('alsen_resp_id', "anon_student_{$student}")
            ->postJson(route('forms.responses.store', $quizForm->slug), [
                'respondent_identifier' => "resp_student_{$student}",
                'answers' => [1 => 'Option 1'],
            ])
            ->assertOk();
    }

    expect(QuizResponse::where('quiz_form_id', $quizForm->id)->count())->toBe(30);
});

test('a single respondent is still rate limited', function () {
    $quizForm = publishedQuiz();

    foreach (range(1, 20) as $attempt) {
        $this->postJson(route('forms.responses.store', $quizForm->slug), [
            'respondent_identifier' => 'resp_spammer',
            'answers' => [1 => 'Option 1'],
        ])->assertOk();
    }

    $this->postJson(route('forms.responses.store', $quizForm->slug), [
        'respondent_identifier' => 'resp_spammer',
        'answers' => [1 => 'Option 1'],
    ])->assertTooManyRequests();
});

test('retrying a submission whose response was lost on the network succeeds without duplicates', function () {
    $quizForm = publishedQuiz(['limitOneResponse' => true]);
    $session = examSessionFor($quizForm, 'resp_weak_wifi');

    $payload = [
        'respondent_identifier' => 'resp_weak_wifi',
        'session_token' => $session->session_token,
        'answers' => [1 => 'Option 2'],
    ];

    $this->postJson(route('forms.responses.store', $quizForm->slug), $payload)->assertOk();
    $this->postJson(route('forms.responses.store', $quizForm->slug), $payload)
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(QuizResponse::where('quiz_form_id', $quizForm->id)->count())->toBe(1)
        ->and($session->refresh()->submitted_at)->not->toBeNull();
});

test('retrying a submission stores it once when more than one response is allowed', function () {
    $quizForm = publishedQuiz(['limitOneResponse' => false]);
    $session = examSessionFor($quizForm, 'resp_lost_response');

    $payload = [
        'respondent_identifier' => 'resp_lost_response',
        'session_token' => $session->session_token,
        'answers' => [1 => 'Option 2'],
    ];

    $this->postJson(route('forms.responses.store', $quizForm->slug), $payload)->assertOk();
    $this->postJson(route('forms.responses.store', $quizForm->slug), $payload)
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(QuizResponse::where('quiz_form_id', $quizForm->id)->count())->toBe(1)
        ->and(ActivityLog::where('event', ActivityLog::EXAM_SUBMITTED)->count())->toBe(1);
});

test('a new attempt after submitting is stored when more than one response is allowed', function () {
    $quizForm = publishedQuiz(['limitOneResponse' => false]);
    $this->actingAs(User::factory()->create(['role' => 'siswa', 'nis' => '2024501']));

    $firstToken = $this->get(route('forms.public', $quizForm->slug))->assertOk()->inertiaProps('session.token');
    $this->postJson(route('forms.responses.store', $quizForm->slug), [
        'session_token' => $firstToken,
        'answers' => [1 => 'Option 1'],
    ])->assertOk();

    // Opening the quiz again ("Submit another response" reloads the page) starts a new attempt.
    $secondToken = $this->get(route('forms.public', $quizForm->slug))->assertOk()->inertiaProps('session.token');
    $this->postJson(route('forms.responses.store', $quizForm->slug), [
        'session_token' => $secondToken,
        'answers' => [1 => 'Option 2'],
    ])->assertOk();

    expect($secondToken)->not->toBe($firstToken)
        ->and(QuizResponse::where('quiz_form_id', $quizForm->id)->orderBy('id')->pluck('answers')->all())
        ->toBe([['1' => 'Option 1'], ['1' => 'Option 2']]);
});

test('a second attempt without the original session token is still rejected', function () {
    $quizForm = publishedQuiz(['limitOneResponse' => true]);
    $session = examSessionFor($quizForm, 'resp_second_try');

    $this->postJson(route('forms.responses.store', $quizForm->slug), [
        'respondent_identifier' => 'resp_second_try',
        'session_token' => $session->session_token,
        'answers' => [1 => 'Option 1'],
    ])->assertOk();

    $this->postJson(route('forms.responses.store', $quizForm->slug), [
        'respondent_identifier' => 'resp_second_try',
        'session_token' => 'forged-token',
        'answers' => [1 => 'Option 2'],
    ])->assertForbidden();

    expect(QuizResponse::where('quiz_form_id', $quizForm->id)->count())->toBe(1);
});

test('guests cannot escape the unlock request limit by changing the respondent identifier', function () {
    $quizForm = publishedQuiz();

    foreach (range(1, 5) as $attempt) {
        $this->withCredentials()
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$attempt])
            ->withCookie('alsen_resp_id', 'anon_same_browser')
            ->postJson(route('forms.public.unlock-requests.store', $quizForm->slug), [
                'respondent_identifier' => "resp_rotated_{$attempt}",
            ])->assertOk();
    }

    $this->withCredentials()
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.6'])
        ->withCookie('alsen_resp_id', 'anon_same_browser')
        ->postJson(route('forms.public.unlock-requests.store', $quizForm->slug), [
            'respondent_identifier' => 'resp_rotated_6',
        ])->assertTooManyRequests();
});
