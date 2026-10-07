<?php

use App\Models\QuizForm;

test('the old built-in default of 10 points for new questions becomes 1', function () {
    $oldDefault = QuizForm::factory()->create(['settings' => ['collectEmail' => false, 'defaultQuestionPoints' => 10]]);
    $ownChoice = QuizForm::factory()->create(['settings' => ['collectEmail' => false, 'defaultQuestionPoints' => 4]]);
    $notSet = QuizForm::factory()->create(['settings' => ['collectEmail' => true]]);
    $questionPoints = $oldDefault->questions;
    $questionPoints[0]['points'] = 10;
    $oldDefault->update(['questions' => $questionPoints]);

    (require database_path('migrations/2026_10_07_011411_reset_default_question_points_to_one.php'))->up();

    expect($oldDefault->refresh()->settings)->toBe(['collectEmail' => false, 'defaultQuestionPoints' => 1])
        // Points already given to questions stay as they are.
        ->and($oldDefault->questions[0]['points'])->toBe(10)
        ->and($ownChoice->refresh()->settings['defaultQuestionPoints'])->toBe(4)
        ->and($notSet->refresh()->settings)->toBe(['collectEmail' => true]);
});
