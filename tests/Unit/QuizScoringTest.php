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
