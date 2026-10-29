<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\ClassMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    Carbon::setTestNow('2026-10-13 09:00:00');
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 60, 'lockOnBlur' => true],
        'questions' => collect(range(1, 4))->map(fn (int $id) => ['id' => $id, 'title' => "Soal {$id}", 'type' => 'Short answer', 'answer' => 'a', 'points' => 1])->all(),
    ]);
});

/**
 * An attempt of a student, started at 08:30 unless said otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function mapAttempt(QuizForm $exam, string $name, array $attributes = []): QuizSession
{
    $student = User::factory()->create(['role' => 'siswa', 'name' => $name, 'kelas' => 'IX-B']);

    return QuizSession::query()->create([
        'quiz_form_id' => $exam->id,
        'user_id' => $student->id,
        'session_token' => Str::random(40),
        'respondent_identifier' => 'user_'.$student->id,
        'started_at' => now()->subMinutes(30),
        'expires_at' => now()->addMinutes(30),
        'last_seen_at' => now()->subSeconds(5),
        'draft_saved_at' => now()->subMinute(),
        ...$attributes,
    ]);
}

test('the proctor sees every student with progress and what needs a look first', function () {
    mapAttempt($this->exam, 'Wati', ['draft_answers' => [1 => 'a', 2 => 'b', 3 => ''], 'doubtful_question_ids' => ['2']]);
    mapAttempt($this->exam, 'Kiki', ['is_locked' => true, 'blur_count' => 2]);
    mapAttempt($this->exam, 'Lala', ['left_at' => now()->subMinute()]);
    mapAttempt($this->exam, 'Oki', ['last_seen_at' => now()->subMinutes(3)]);
    mapAttempt($this->exam, 'Ida', ['draft_saved_at' => now()->subMinutes(8)]);
    mapAttempt($this->exam, 'Beni', ['started_at' => null, 'expires_at' => null]);
    $done = mapAttempt($this->exam, 'Sari', ['submitted_at' => now()->subMinutes(2)]);
    QuizResponse::query()->create(['quiz_form_id' => $this->exam->id, 'quiz_session_id' => $done->id, 'user_id' => $done->user_id, 'answers' => [1 => 'a', 2 => 'a', 3 => 'a', 4 => ''], 'score' => 3]);
    // Yesterday's sitting is not on today's map.
    mapAttempt($this->exam, 'Kemarin', ['submitted_at' => now()->subDay()])->forceFill(['updated_at' => now()->subDay(), 'last_seen_at' => now()->subDay()])->saveQuietly();

    $map = $this->actingAs($this->teacher)->getJson(route('forms.class-map', $this->exam))->assertOk();

    expect(collect($map->json('students'))->map(fn (array $student) => "{$student['name']}:{$student['status']}")->all())
        ->toBe(['Kiki:locked', 'Lala:left', 'Oki:offline', 'Ida:idle', 'Wati:working', 'Beni:not_started', 'Sari:submitted']);

    $map->assertJsonPath('counts.locked', 1)
        ->assertJsonPath('counts.submitted', 1)
        ->assertJsonPath('students.0.blurCount', 2)
        ->assertJsonPath('students.3.idleMinutes', 8)
        ->assertJsonPath('students.4.answered', 2)
        ->assertJsonPath('students.4.total', 4)
        ->assertJsonPath('students.4.doubts', 1)
        ->assertJsonPath('students.4.remainingSeconds', 1800)
        ->assertJsonPath('students.4.kelas', 'IX-B')
        ->assertJsonPath('students.6.answered', 3)
        ->assertJsonPath('students.6.remainingSeconds', null);
});

test('a student who took the exam again shows once, with the latest attempt', function () {
    $first = mapAttempt($this->exam, 'Rina', ['submitted_at' => now()->subMinutes(20)]);
    QuizSession::query()->create([
        'quiz_form_id' => $this->exam->id, 'user_id' => $first->user_id, 'session_token' => Str::random(40), 'respondent_identifier' => $first->respondent_identifier,
        'started_at' => now()->subMinutes(5), 'last_seen_at' => now(), 'draft_saved_at' => now(),
    ]);

    $this->actingAs($this->teacher)->getJson(route('forms.class-map', $this->exam))
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.status', 'working');
});

test('only the exam\'s teachers see the map', function () {
    $this->actingAs(User::factory()->create(['role' => 'siswa']))->getJson(route('forms.class-map', $this->exam))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'guru']))->getJson(route('forms.class-map', $this->exam))->assertForbidden();
});

test('a student whose answers have not reached the server for two minutes shows as unsaved', function () {
    $stuck = mapAttempt($this->exam, 'Dodi', ['draft_answers' => [1 => 'a'], 'draft_saved_at' => now()->subMinutes(4)]);
    $fine = mapAttempt($this->exam, 'Eka');

    // The pages tell with their status checks since when they hold answers the server has not got.
    $this->getJson(route('forms.responses.status', ['quizForm' => $this->exam->slug, 'session_token' => $stuck->session_token, 'unsynced_since' => now()->subMinutes(3)->getTimestampMs()]))->assertOk();
    $this->getJson(route('forms.responses.status', ['quizForm' => $this->exam->slug, 'session_token' => $fine->session_token, 'unsynced_since' => now()->subSeconds(20)->getTimestampMs()]))->assertOk();

    $map = $this->actingAs($this->teacher)->getJson(route('forms.class-map', $this->exam))->assertOk();
    $students = collect($map->json('students'))->keyBy('name');

    expect($students['Dodi']['status'])->toBe('unsaved')
        ->and($students['Dodi']['unsavedSeconds'])->toBe(180)
        ->and($students['Dodi']['savedSeconds'])->toBe(240)
        ->and($students['Eka']['status'])->toBe('working')
        ->and($map->json('counts.unsaved'))->toBe(1);

    // Once the draft gets through, the student is working again.
    $this->app['auth']->forgetGuards();
    $this->postJson(route('forms.responses.draft', $this->exam->slug), [
        'session_token' => $stuck->session_token,
        'answers' => [1 => 'a', 2 => 'b'],
        'pending_since' => now()->subMinutes(3)->getTimestampMs(),
    ])->assertOk();

    expect($stuck->fresh()->unsynced_since)->toBeNull()
        ->and($stuck->fresh()->max_sync_delay_seconds)->toBe(180)
        ->and(app(ClassMap::class)->statusOf($this->exam, $stuck->fresh()))->toBe('working');

    // "0": nothing waits on the device.
    $this->getJson(route('forms.responses.status', ['quizForm' => $this->exam->slug, 'session_token' => $fine->session_token, 'unsynced_since' => 0]))->assertOk();
    expect($fine->fresh()->unsynced_since)->toBeNull();
});

test('a device clock far off cannot put the unsaved time outside the attempt', function () {
    $attempt = mapAttempt($this->exam, 'Fani');

    $this->getJson(route('forms.responses.status', ['quizForm' => $this->exam->slug, 'session_token' => $attempt->session_token, 'unsynced_since' => now()->subYear()->getTimestampMs()]))->assertOk();
    expect($attempt->fresh()->unsynced_since->toDateTimeString())->toBe($attempt->started_at->toDateTimeString());

    $this->getJson(route('forms.responses.status', ['quizForm' => $this->exam->slug, 'session_token' => $attempt->session_token, 'unsynced_since' => now()->addDay()->getTimestampMs()]))->assertOk();
    expect($attempt->fresh()->unsynced_since->toDateTimeString())->toBe(now()->toDateTimeString());
});
