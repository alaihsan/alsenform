<?php

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizSessionSnapshot;
use App\Models\ServerError;

/*
 * Records past their retention (config/maintenance.php) are removed by model:prune, which lan:serve
 * runs every day and the scheduler runs at night: the activity report after a school year, copies
 * of answers during exams after 90 days, server errors marked as solved after 90 days.
 */

test('old activities, answer copies and solved server errors are removed, the rest is kept', function () {
    $oldActivity = ActivityLog::factory()->create(['created_at' => now()->subDays(366)]);
    $activity = ActivityLog::factory()->create(['created_at' => now()->subDays(364)]);

    $attempt = startedAttempt(QuizForm::factory()->create(['published_at' => now()]));
    $oldCopy = QuizSessionSnapshot::query()->create(['quiz_session_id' => $attempt->id, 'answers' => [1 => 'a'], 'answered' => 1, 'created_at' => now()->subDays(91)]);
    $copy = QuizSessionSnapshot::query()->create(['quiz_session_id' => $attempt->id, 'answers' => [1 => 'a'], 'answered' => 1, 'created_at' => now()->subDays(10)]);

    $solvedLongAgo = ServerError::factory()->resolved()->create(['last_seen_at' => now()->subDays(100)]);
    $solvedRecently = ServerError::factory()->resolved()->create(['last_seen_at' => now()->subDays(10)]);
    // An open fault stays however old, until someone looks at it.
    $open = ServerError::factory()->create(['last_seen_at' => now()->subDays(200)]);

    $this->artisan('model:prune')->assertSuccessful();

    expect(ActivityLog::query()->pluck('id')->all())->toBe([$activity->id])
        ->and(QuizSessionSnapshot::query()->pluck('id')->all())->toBe([$copy->id])
        ->and(ServerError::query()->orderBy('id')->pluck('id')->all())->toBe([$solvedRecently->id, $open->id])
        ->and(ActivityLog::query()->find($oldActivity->id))->toBeNull()
        ->and(QuizSessionSnapshot::query()->find($oldCopy->id))->toBeNull()
        ->and(ServerError::query()->find($solvedLongAgo->id))->toBeNull();
});

test('the retention of the activity report can be set in .env', function () {
    config(['maintenance.retention_days.activity_logs' => 30]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(31)]);
    $kept = ActivityLog::factory()->create(['created_at' => now()->subDays(29)]);

    $this->artisan('model:prune')->assertSuccessful();

    expect(ActivityLog::query()->pluck('id')->all())->toBe([$kept->id]);
});
