<?php

use App\Actions\SubmitExpiredAttempts;
use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\ActivityReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-23 08:00:00'));
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->create([
        'user_id' => $this->teacher->id,
        'published_at' => now(),
        'settings' => ['collectEmail' => false, 'showProgress' => true, 'shuffleQuestions' => false, 'timeLimit' => 60],
    ]);
});

/**
 * An attempt of the exam, started an hour before it ends unless told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function attemptEndingAt(QuizForm $exam, ?Carbon $expiresAt, array $attributes = []): QuizSession
{
    return QuizSession::create(array_merge([
        'quiz_form_id' => $exam->id,
        'respondent_identifier' => 'anon_'.Str::random(16),
        'respondent_name' => 'Budi Santoso',
        'respondent_class' => '8B',
        'session_token' => Str::random(40),
        'started_at' => $expiresAt?->copy()->subMinutes(60),
        'expires_at' => $expiresAt,
    ], $attributes));
}

function extendTime(QuizForm $exam, mixed $minutes): TestResponse
{
    return test()->postJson(route('forms.sessions.extend-time', $exam), ['minutes' => $minutes]);
}

test('the teacher gives every attempt still in progress the extra minutes', function () {
    $running = attemptEndingAt($this->exam, now()->addMinutes(5));
    $locked = attemptEndingAt($this->exam, now()->addMinutes(20), ['is_locked' => true]);
    // The page's time ran out during the outage, nothing handed in yet.
    $justEnded = attemptEndingAt($this->exam, now()->subMinutes(10));
    $leftLongAgo = attemptEndingAt($this->exam, now()->subMinutes(45));
    $submitted = attemptEndingAt($this->exam, now()->addMinutes(5), ['submitted_at' => now()->subMinute()]);
    $notStarted = attemptEndingAt($this->exam, null);
    $otherExam = attemptEndingAt(QuizForm::factory()->create(['published_at' => now()]), now()->addMinutes(5));

    $this->actingAs($this->teacher);
    extendTime($this->exam, 10)
        ->assertOk()
        ->assertJson(['minutes' => 10, 'extended' => 3])
        ->assertJsonPath('message', 'Waktu ditambah 10 menit untuk 3 siswa yang sedang mengerjakan.');

    expect($running->refresh()->expires_at->toDateTimeString())->toBe('2026-11-23 08:15:00')
        ->and($locked->refresh()->expires_at->toDateTimeString())->toBe('2026-11-23 08:30:00')
        ->and($justEnded->refresh()->expires_at->toDateTimeString())->toBe('2026-11-23 08:00:00')
        ->and($leftLongAgo->refresh()->expires_at->toDateTimeString())->toBe('2026-11-23 07:15:00')
        ->and($submitted->refresh()->expires_at->toDateTimeString())->toBe('2026-11-23 08:05:00')
        ->and($notStarted->refresh()->expires_at)->toBeNull()
        ->and($otherExam->refresh()->expires_at->toDateTimeString())->toBe('2026-11-23 08:05:00');
});

test('giving extra time twice adds both', function () {
    $attempt = attemptEndingAt($this->exam, now()->addMinutes(5));

    $this->actingAs($this->teacher);
    extendTime($this->exam, 10)->assertOk();
    extendTime($this->exam, 5)->assertOk()->assertJsonPath('extended', 1);

    expect($attempt->refresh()->expires_at->toDateTimeString())->toBe('2026-11-23 08:20:00');
});

test('nobody working on the exam is told, and nothing is changed', function () {
    attemptEndingAt($this->exam, now()->addMinutes(5), ['submitted_at' => now()]);

    $this->actingAs($this->teacher);
    extendTime($this->exam, 10)
        ->assertOk()
        ->assertJsonPath('extended', 0)
        ->assertJsonPath('message', 'Tidak ada siswa yang sedang mengerjakan ujian ini. Tidak ada waktu yang ditambahkan.');
});

test('the extra time is recorded in the activity log', function () {
    attemptEndingAt($this->exam, now()->addMinutes(5));
    attemptEndingAt($this->exam, now()->addMinutes(6));

    $this->actingAs($this->teacher);
    extendTime($this->exam, 15)->assertOk();

    $log = ActivityLog::query()->sole();
    expect($log)
        ->event->toBe(ActivityLog::EXAM_TIME_EXTENDED)
        ->user_id->toBe($this->teacher->id)
        ->quiz_form_id->toBe($this->exam->id)
        ->meta->toBe(['minutes' => 15, 'attempts' => 2]);

    $row = app(ActivityReport::class)->row($log, $this->teacher);
    expect($row['eventLabel'])->toBe('Tambah waktu ujian')
        ->and($row['note'])->toBe('+15 menit untuk 2 siswa');
});

test('collaborators and admins of the exam may give extra time', function (string $who) {
    $attempt = attemptEndingAt($this->exam, now()->addMinutes(5));
    $user = $who === 'admin'
        ? User::factory()->create(['role' => 'admin', 'is_admin' => true])
        : User::factory()->create(['role' => 'guru']);
    if ($who === 'collaborator') {
        $this->exam->collaborators()->attach($user->id);
    }

    $this->actingAs($user);
    extendTime($this->exam, 10)->assertOk()->assertJsonPath('extended', 1);

    expect($attempt->refresh()->expires_at->toDateTimeString())->toBe('2026-11-23 08:15:00');
})->with(['collaborator', 'admin']);

test('other teachers, students and guests cannot give extra time', function (?string $role) {
    $attempt = attemptEndingAt($this->exam, now()->addMinutes(5));

    if ($role === null) {
        extendTime($this->exam, 10)->assertUnauthorized();
    } else {
        $this->actingAs(User::factory()->create(['role' => $role, 'nis' => $role === 'siswa' ? '12345' : null]));
        extendTime($this->exam, 10)->assertForbidden();
    }

    expect($attempt->refresh()->expires_at->toDateTimeString())->toBe('2026-11-23 08:05:00')
        ->and(ActivityLog::query()->count())->toBe(0);
})->with([
    'another teacher' => 'guru',
    'a student' => 'siswa',
    'a guest' => null,
]);

test('the extra time is 1 to 120 whole minutes', function (mixed $minutes) {
    $attempt = attemptEndingAt($this->exam, now()->addMinutes(5));

    $this->actingAs($this->teacher);
    extendTime($this->exam, $minutes)->assertUnprocessable()->assertJsonValidationErrors('minutes');

    expect($attempt->refresh()->expires_at->toDateTimeString())->toBe('2026-11-23 08:05:00');
})->with([0, -5, 121, 1.5, 'abc', null]);

test('the smallest and the largest extra time are accepted', function (int $minutes, string $end) {
    $attempt = attemptEndingAt($this->exam, now()->addMinutes(5));

    $this->actingAs($this->teacher);
    extendTime($this->exam, $minutes)->assertOk();

    expect($attempt->refresh()->expires_at->toDateTimeString())->toBe($end);
})->with([
    [1, '2026-11-23 08:06:00'],
    [120, '2026-11-23 10:05:00'],
]);

test('the open exam page is told the new end by its status and lock polls', function () {
    $attempt = attemptEndingAt($this->exam, now()->addMinutes(5));
    $status = fn () => $this->getJson(route('forms.responses.status', ['quizForm' => $this->exam->slug, 'session_token' => $attempt->session_token]));

    $status()->assertOk()
        ->assertJsonPath('expires_at', now()->addMinutes(5)->toISOString())
        ->assertJsonPath('server_time', now()->toISOString());

    $this->actingAs($this->teacher);
    extendTime($this->exam, 10)->assertOk();
    auth()->logout();

    $this->travel(30)->seconds();

    $status()->assertOk()
        ->assertJsonPath('expires_at', Carbon::parse('2026-11-23 08:15:00')->toISOString())
        ->assertJsonPath('server_time', now()->toISOString())
        ->assertJsonPath('submitted', false);

    $this->postJson(route('forms.responses.lock', $this->exam->slug), ['session_token' => $attempt->session_token, 'reason' => 'unload'])
        ->assertOk()
        ->assertJsonPath('expires_at', Carbon::parse('2026-11-23 08:15:00')->toISOString());
});

test('a page that hands in at its old end is told the new end, and the student continues', function () {
    $attempt = attemptEndingAt($this->exam, now()->addMinutes(5));

    $this->actingAs($this->teacher);
    extendTime($this->exam, 10)->assertOk();
    auth()->logout();

    // The old end: the page had not polled since the teacher added the time.
    $this->travel(5)->minutes();
    $handIn = fn () => $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'respondent_identifier' => $attempt->respondent_identifier,
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Option 2'],
        'is_timeout' => true,
    ]);

    $handIn()->assertConflict()
        ->assertJsonPath('extra_time', true)
        ->assertJsonPath('expires_at', Carbon::parse('2026-11-23 08:15:00')->toISOString())
        ->assertJsonPath('server_time', now()->toISOString());

    expect(QuizResponse::query()->count())->toBe(0)
        ->and($attempt->refresh()->submitted_at)->toBeNull();

    // Answers keep being saved after the old end.
    $this->travel(2)->minutes();
    $this->postJson(route('forms.responses.draft', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Option 1'],
    ])->assertOk()->assertJsonPath('saved', true);

    // At the new end the time is really up.
    $this->travel(8)->minutes();
    $handIn()->assertOk();

    expect(QuizResponse::query()->sole())
        ->is_timeout->toBeTrue()
        ->late_seconds->toBeNull();
});

test('a timeout hand-in of an attempt without extra time is taken as before', function () {
    $attempt = attemptEndingAt($this->exam, now()->addMinutes(30), ['started_at' => now()->subMinutes(30)]);

    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'respondent_identifier' => $attempt->respondent_identifier,
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Option 2'],
        'is_timeout' => true,
    ])->assertOk();

    expect(QuizResponse::query()->sole()->is_timeout)->toBeTrue();
});

test('the server does not hand in an attempt at its old end once extra time is given', function () {
    $attempt = attemptEndingAt($this->exam, now()->addMinutes(5), ['draft_answers' => [1 => 'Option 1']]);

    $this->actingAs($this->teacher);
    extendTime($this->exam, 10)->assertOk();

    $this->travel(7)->minutes();
    expect(app(SubmitExpiredAttempts::class)->handle($this->exam))->toBe(0)
        ->and($attempt->refresh()->submitted_at)->toBeNull();

    $this->travel(10)->minutes();
    expect(app(SubmitExpiredAttempts::class)->handle($this->exam))->toBe(1)
        ->and(QuizResponse::query()->sole()->created_at->toDateTimeString())->toBe('2026-11-23 08:15:00');
});
