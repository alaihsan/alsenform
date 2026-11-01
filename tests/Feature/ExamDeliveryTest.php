<?php

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
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

test('the errors are those the students met, not those of the teachers editing the exam', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $collaborator = User::factory()->create(['role' => 'guru']);
    $student = User::factory()->create(['role' => 'siswa', 'name' => 'Ani', 'kelas' => '7A']);
    $exam = QuizForm::factory()->for($teacher)->create(['published_at' => now(), 'slug' => 'ujian-ipa']);
    $exam->collaborators()->attach($collaborator->id);
    startedAttempt($exam, $student);

    // Saving the exam failed twice for the teacher and once for a collaborator: not the students'.
    ActivityLog::factory()->count(2)->create(['event' => ActivityLog::SERVER_ERROR, 'quiz_form_id' => $exam->id, 'user_id' => $teacher->id, 'meta' => ['path' => '/forms/'.$exam->id, 'method' => 'PATCH', 'status' => 500, 'reference' => 'ERR-AAAAAA']]);
    ActivityLog::factory()->create(['event' => ActivityLog::SERVER_ERROR, 'quiz_form_id' => $exam->id, 'user_id' => $collaborator->id, 'meta' => ['path' => '/forms/'.$exam->id, 'method' => 'PATCH', 'status' => 500]]);
    // The exam page itself counts, also for the teacher trying it (or interviewing).
    ActivityLog::factory()->create(['event' => ActivityLog::PAGE_FAILED, 'quiz_form_id' => $exam->id, 'user_id' => $teacher->id, 'meta' => ['code' => 'FF-302', 'path' => '/forms/ujian-ipa']]);
    ActivityLog::factory()->create([
        'event' => ActivityLog::PAGE_FAILED,
        'quiz_form_id' => $exam->id,
        'user_id' => $student->id,
        'device' => 'iPhone',
        'os' => 'iOS 18.7',
        'browser' => 'Safari 27.0.1',
        'meta' => ['code' => 'FF-302', 'path' => '/forms/ujian-ipa', 'message' => "null is not an object (evaluating 'window.history.state.scrollRegions')"],
        'created_at' => now()->addMinute(),
    ]);

    $delivery = app(ExamDelivery::class);

    expect($delivery->of($exam))->toMatchArray(['errors' => 2, 'errorsPer100' => 200.0])
        ->and($delivery->recentErrors($exam))->toHaveCount(2)
        ->and($delivery->recentErrors($exam)[0])->toMatchArray([
            'kind' => 'Halaman gagal dimuat',
            'person' => 'Ani',
            'group' => '7A',
            'device' => 'iPhone · iOS 18.7 · Safari 27.0.1',
            'cause' => 'Halaman gagal ditampilkan (FF-302)',
            'detail' => "null is not an object (evaluating 'window.history.state.scrollRegions')",
        ]);

    // The results page lists them, with the way to Laporan for everything else of the exam.
    $payload = app(QuizFormPayloads::class)->responses($exam)['delivery'];
    expect($payload['recentErrors'])->toHaveCount(2)
        ->and($payload['reportUrl'])->toContain('form='.$exam->id);
});
