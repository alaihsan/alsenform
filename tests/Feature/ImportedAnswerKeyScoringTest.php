<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;

/**
 * Submit answers to a quiz built from imported questions and return the score.
 *
 * @param  array<int, array<string, mixed>>  $questions
 * @param  array<int, mixed>  $answers
 */
function scoreFor(array $questions, array $answers): int
{
    $quizForm = QuizForm::factory()->create([
        'published_at' => now(),
        'questions' => $questions,
        'settings' => ['collectEmail' => false, 'showProgress' => false, 'shuffleQuestions' => false, 'isQuiz' => true],
    ]);

    test()->postJson(route('forms.responses.store', $quizForm->slug), [
        'respondent_identifier' => 'resp_'.uniqid(),
        'answers' => $answers,
    ])->assertOk();

    return (int) QuizResponse::where('quiz_form_id', $quizForm->id)->latest('id')->value('score');
}

function shortAnswer(int $id, string $key, int $points = 1): array
{
    return ['id' => $id, 'title' => 'Soal '.$id, 'type' => 'Short answer', 'options' => [], 'answer' => $key, 'required' => false, 'points' => $points];
}

test('short answers accept every alternative of a fill in the blank key', function () {
    $questions = [shortAnswer(1, 'Jakarta | DKI Jakarta')];

    expect(scoreFor($questions, [1 => 'dki  jakarta']))->toBe(1)
        ->and(scoreFor($questions, [1 => 'JAKARTA']))->toBe(1)
        ->and(scoreFor($questions, [1 => 'Bandung']))->toBe(0);
});

test('numeric answers are compared by value and may use a tolerance range', function () {
    $questions = [shortAnswer(1, '9.5..10.5', 2), shortAnswer(2, '3.5', 1)];

    expect(scoreFor($questions, [1 => '10', 2 => '3,50']))->toBe(3)
        ->and(scoreFor($questions, [1 => '10,4', 2 => '3.5']))->toBe(3)
        ->and(scoreFor($questions, [1 => '11', 2 => '35']))->toBe(0);
});

test('essays and questions without an answer key are never scored automatically', function () {
    $questions = [
        ['id' => 1, 'title' => 'Esai', 'type' => 'Paragraph', 'options' => [], 'answer' => 'Contoh jawaban', 'required' => false, 'points' => 5],
        ['id' => 2, 'title' => 'Menjodohkan', 'type' => 'Multiple-choice grid', 'rows' => ['A'], 'columns' => ['1'], 'answer' => [], 'required' => false, 'points' => 3],
    ];

    expect(scoreFor($questions, [1 => 'Contoh jawaban', 2 => ['0' => 0]]))->toBe(0);
});
