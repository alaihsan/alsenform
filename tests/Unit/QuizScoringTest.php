<?php

use App\Support\QuizScoring;

beforeEach(function () {
    $this->scoring = new QuizScoring;
});

test('choice questions accept the option text for an index key', function (string $type) {
    $question = ['id' => 1, 'type' => $type, 'options' => ['Mars', 'Jupiter'], 'answer' => 1, 'points' => 5];

    expect($this->scoring->isCorrect($question, 'Jupiter'))->toBeTrue()
        ->and($this->scoring->isCorrect($question, 'Mars'))->toBeFalse()
        ->and($this->scoring->score([$question], [1 => 'Jupiter']))->toBe(5);
})->with(['Multiple choice', 'Drop-down']);

test('checkboxes need exactly the correct options', function () {
    $question = ['id' => 1, 'type' => 'Checkboxes', 'options' => ['2', '4', '5'], 'answer' => [0, 2]];

    expect($this->scoring->isCorrect($question, ['5', '2']))->toBeTrue()
        ->and($this->scoring->isCorrect($question, ['2']))->toBeFalse()
        ->and($this->scoring->isCorrect($question, ['2', '4', '5']))->toBeFalse();
});

test('grids compare every row', function () {
    $single = ['id' => 1, 'type' => 'Multiple-choice grid', 'answer' => ['0' => 1, '1' => 0]];
    $multiple = ['id' => 2, 'type' => 'Tick box grid', 'answer' => ['0' => [0, 2], '1' => [1]]];

    expect($this->scoring->isCorrect($single, ['0' => 1, '1' => 0]))->toBeTrue()
        ->and($this->scoring->isCorrect($single, ['0' => 1, '1' => 1]))->toBeFalse()
        ->and($this->scoring->isCorrect($multiple, ['0' => [2, 0], '1' => [1]]))->toBeTrue()
        ->and($this->scoring->isCorrect($multiple, ['0' => [0], '1' => [1]]))->toBeFalse();
});

test('essays and questions without a key are not scored automatically', function () {
    expect($this->scoring->isCorrect(['type' => 'Paragraph', 'answer' => 'contoh'], 'jawaban'))->toBeNull()
        ->and($this->scoring->isCorrect(['type' => 'Short answer', 'answer' => ''], 'jawaban'))->toBeNull()
        ->and($this->scoring->isAutoScored(['type' => 'Linear scale']))->toBeFalse();
});

test('unexpected answer shapes never break scoring', function () {
    $questions = [
        ['id' => 1, 'type' => 'Multiple choice', 'options' => ['A', 'B'], 'answer' => 0],
        ['id' => 2, 'type' => 'Linear scale', 'answer' => '3'],
        ['id' => 3, 'type' => 'Short answer', 'answer' => '56'],
    ];

    expect($this->scoring->score($questions, [1 => ['A'], 2 => ['3'], 3 => ['56']]))->toBe(0);
});

test('questions are worth 1 point unless another weight is given', function () {
    expect($this->scoring->points(['id' => 1, 'type' => 'Short answer']))->toBe(1)
        ->and($this->scoring->points(['id' => 1, 'type' => 'Short answer', 'points' => 3]))->toBe(3);
});

test('the grade is the share of points that can be earned on a 0 to 100 scale', function () {
    $questions = [
        ['id' => 1, 'type' => 'Short answer', 'answer' => 'a'],
        ['id' => 2, 'type' => 'Short answer', 'answer' => 'b', 'points' => 2],
        ['id' => 3, 'type' => 'Paragraph', 'answer' => 'rubrik', 'points' => 5],
        ['id' => 4, 'type' => 'Linear scale', 'answer' => '', 'points' => 1],
    ];

    // Only the two questions with an answer key count: 1 + 2 points.
    expect($this->scoring->maxPoints($questions))->toBe(3)
        ->and($this->scoring->grade($this->scoring->score($questions, [1 => 'a', 2 => 'b']), 3))->toBe(100.0)
        ->and($this->scoring->grade($this->scoring->score($questions, [2 => 'b']), 3))->toBe(66.67)
        ->and($this->scoring->grade(0, 3))->toBe(0.0)
        ->and($this->scoring->grade(5, 3))->toBe(100.0)
        ->and($this->scoring->grade(1, 0))->toBe(0.0);
});
