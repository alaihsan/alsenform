<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
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
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
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
