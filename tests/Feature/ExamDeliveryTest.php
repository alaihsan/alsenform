<?php

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Services\ExamDelivery;
use App\Support\QuizFormPayloads;

/*
 * Pengiriman Jawaban: how well the answers of an exam got to the server, in three figures.
 */

test('an exam tells how many answers reached the server in time, went through at once, and the errors per hundred students', function () {
    $exam = QuizForm::factory()->create(['published_at' => now()]);
    $other = QuizForm::factory()->create(['published_at' => now()]);

    // Four students: three saved within 30 seconds, one waited 95 seconds on weak Wi-Fi.
    foreach ([4, 12, 30, 95] as $index => $delay) {
        $attempt = startedAttempt($exam, attributes: ['max_sync_delay_seconds' => $delay]);
        QuizResponse::query()->create([
            'quiz_form_id' => $exam->id,
            'quiz_session_id' => $attempt->id,
            'respondent_identifier' => $attempt->respondent_identifier,
            'answers' => [1 => 'Option 1'],
            'submit_attempts' => $index === 3 ? 4 : 1,
        ]);
    }
    // One page that never told (an older version) counts as a student but not in the delay figure.
    startedAttempt($exam);
    QuizResponse::query()->create(['quiz_form_id' => $exam->id, 'respondent_identifier' => 'anon_auto', 'answers' => [], 'is_auto_submitted' => true, 'is_timeout' => true]);

    ActivityLog::factory()->create(['event' => ActivityLog::PAGE_FAILED, 'quiz_form_id' => $exam->id]);
    ActivityLog::factory()->create(['event' => ActivityLog::SERVER_ERROR, 'quiz_form_id' => $exam->id]);
    ActivityLog::factory()->create(['event' => ActivityLog::SERVER_ERROR, 'quiz_form_id' => $other->id]);
    ActivityLog::factory()->examSubmitted($exam)->create();

    expect(app(ExamDelivery::class)->of($exam))->toBe([
        'attempts' => 5,
        'savedInTime' => 75.0,
        'savedInTimeOf' => 4,
        'slowestSaveSeconds' => 95,
        'firstTry' => 75.0,
        'firstTryOf' => 4,
        'autoSubmitted' => 1,
        'errors' => 2,
        'errorsPer100' => 40.0,
    ]);

    expect(app(QuizFormPayloads::class)->responses($exam)['delivery']['savedInTime'])->toBe(75.0);
});

test('an exam nobody took has no figures yet', function () {
    expect(app(ExamDelivery::class)->of(QuizForm::factory()->create()))->toMatchArray([
        'attempts' => 0,
        'savedInTime' => null,
        'firstTry' => null,
        'errorsPer100' => null,
    ]);
});
