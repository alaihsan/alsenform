<?php

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use App\Support\DisplayTime;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru', 'name' => 'Bu Rina']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'title' => '<p>Ujian <strong>Bahasa Inggris</strong></p>',
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 45, 'maxAttempts' => 1],
        'questions' => [
            ['id' => 1, 'title' => 'Choose the right word', 'type' => 'Multiple choice', 'options' => ['go', 'goes'], 'answer' => 'goes', 'points' => 1],
        ],
    ]);
    $this->openPreview = fn () => $this->get(route('forms.public', $this->exam->slug));
    $this->closePreview = fn (string $token) => $this->postJson(route('forms.preview.close', $this->exam->slug), ['session_token' => $token]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the people who edit the exam see it as a preview; students and interviewers do not', function () {
    Carbon::setTestNow('2026-11-24 01:15:00');

    expect($this->actingAs($this->teacher)->get(route('forms.public', $this->exam->slug))->inertiaProps('preview'))->toBe([
        'openedAt' => now()->toISOString(),
        'openedAtLabel' => '08:15',
        'closeUrl' => route('forms.preview.close', $this->exam->slug),
        'editUrl' => route('forms.edit', $this->exam->slug),
    ]);

    $collaborator = User::factory()->create(['role' => 'guru']);
    $this->exam->collaborators()->attach($collaborator->id);
    expect($this->actingAs($collaborator)->get(route('forms.public', $this->exam->slug))->inertiaProps('preview'))->not->toBeNull();

    $student = User::factory()->create(['role' => 'siswa']);
    expect($this->actingAs($student)->get(route('forms.public', $this->exam->slug))->inertiaProps('preview'))->toBeNull();

    // Mode pewawancara: the interviewer fills the form in for real candidates.
    $interview = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'optionScoring' => true, 'interviewMode' => true],
    ]);
    expect($this->actingAs($this->teacher)->get(route('forms.public', $interview->slug))->inertiaProps('preview'))->toBeNull();
});

test('Tutup pratinjau ends the preview and shows when it was opened and closed and how long it took', function () {
    // 08:15:03 WIB on Tuesday 24 November 2026.
    Carbon::setTestNow('2026-11-24 01:15:03');
    $this->actingAs($this->teacher);
    $token = ($this->openPreview)()->inertiaProps('session.token');
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token])->assertOk();
    $session = QuizSession::query()->where('session_token', $token)->sole();

    Carbon::setTestNow('2026-11-24 01:32:40');
    $summaryUrl = ($this->closePreview)($token)
        ->assertOk()
        ->assertExactJson(['url' => route('forms.preview.show', [$this->exam->slug, $session->id])])
        ->json('url');

    $session->refresh();
    expect($session->preview_closed_at->equalTo(now()))->toBeTrue()
        ->and($session->submitted_at->equalTo(now()))->toBeTrue()
        ->and($session->previewSeconds())->toBe(17 * 60 + 37)
        ->and(QuizResponse::query()->count())->toBe(0);

    $log = ActivityLog::query()->where('event', ActivityLog::PREVIEW_CLOSED)->sole();
    expect($log->user_id)->toBe($this->teacher->id)
        ->and($log->quiz_form_id)->toBe($this->exam->id)
        ->and($log->meta)->toBe(['opened_at' => '2026-11-24T01:15:03.000000Z', 'closed_at' => '2026-11-24T01:32:40.000000Z', 'seconds' => 1057]);

    $this->get($summaryUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('PreviewSummary')
        ->where('quizForm.title', 'Ujian Bahasa Inggris')
        ->where('quizForm.editUrl', route('forms.edit', $this->exam->slug))
        ->where('quizForm.previewUrl', route('forms.public', $this->exam->slug))
        ->where('preview.openedAt', ['iso' => '2026-11-24T01:15:03.000000Z', 'date' => 'Selasa, 24 November 2026', 'time' => '08:15:03', 'timezone' => 'WIB'])
        ->where('preview.closedAt.time', '08:32:40')
        ->where('preview.seconds', 1057)
        ->where('preview.duration', '17 menit 37 detik')
        ->where('preview.handedIn', false));

    // In Laporan Aktivitas, with its times and length.
    $row = collect($this->get(route('reports.index'))->inertiaProps('activities.rows'))->firstWhere('event', ActivityLog::PREVIEW_CLOSED);
    expect($row)->toMatchArray(['eventLabel' => 'Tutup pratinjau', 'note' => 'Pratinjau 08:15:03–08:32:40 · 17 menit 37 detik']);

    // Opened again: a new preview, with its own time.
    $next = ($this->openPreview)();
    expect($next->inertiaProps('session.token'))->not->toBe($token)
        ->and($next->inertiaProps('preview.openedAt'))->toBe(now()->toISOString());
});

test('a preview closed twice (a double click) is recorded once', function () {
    $this->actingAs($this->teacher);
    $token = ($this->openPreview)()->inertiaProps('session.token');

    $first = ($this->closePreview)($token)->assertOk()->json('url');
    $this->travel(5)->seconds();
    expect(($this->closePreview)($token)->assertOk()->json('url'))->toBe($first)
        ->and(ActivityLog::query()->where('event', ActivityLog::PREVIEW_CLOSED)->count())->toBe(1);
});

test('a preview handed in before it was closed says so', function () {
    Carbon::setTestNow('2026-11-24 01:00:00');
    $this->actingAs($this->teacher);
    $token = ($this->openPreview)()->inertiaProps('session.token');
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token])->assertOk();

    Carbon::setTestNow('2026-11-24 01:04:00');
    $this->postJson(route('forms.responses.store', $this->exam->slug), ['session_token' => $token, 'answers' => [1 => 'goes']])->assertSuccessful();

    Carbon::setTestNow('2026-11-24 01:05:30');
    $this->get(($this->closePreview)($token)->json('url'))->assertInertia(fn (Assert $page) => $page
        ->where('preview.duration', '5 menit 30 detik')
        ->where('preview.handedIn', true));
});

test('the preview is not held to the limit of attempts', function () {
    QuizResponse::factory()->for($this->exam)->create(['user_id' => $this->teacher->id]);
    $this->actingAs($this->teacher);

    $page = ($this->openPreview)();
    expect($page->inertiaProps('accessRestricted'))->toBeFalse()
        ->and($page->inertiaProps('attempts.max'))->toBeNull();
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $page->inertiaProps('session.token')])->assertOk();

    // A student who used their attempt still cannot start again.
    $student = User::factory()->create(['role' => 'siswa']);
    QuizResponse::factory()->for($this->exam)->create(['user_id' => $student->id]);
    expect($this->actingAs($student)->get(route('forms.public', $this->exam->slug))->inertiaProps('accessRestricted'))->toBeTrue();
});

test('only the people who edit the exam close a preview, and only their own', function () {
    $collaborator = User::factory()->create(['role' => 'guru']);
    $this->exam->collaborators()->attach($collaborator->id);
    $teacherToken = $this->actingAs($this->teacher)->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');
    $collaboratorToken = $this->actingAs($collaborator)->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');

    $this->actingAs(User::factory()->create(['role' => 'guru']));
    ($this->closePreview)($teacherToken)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'siswa']));
    ($this->closePreview)($teacherToken)->assertForbidden();

    // Someone else's preview is not found.
    $this->actingAs($collaborator);
    ($this->closePreview)($teacherToken)->assertNotFound();
    $summaryUrl = ($this->closePreview)($collaboratorToken)->assertOk()->json('url');
    $this->actingAs($this->teacher)->get($summaryUrl)->assertNotFound();

    // Not closed yet: no summary.
    $teacherSession = QuizSession::query()->where('session_token', $teacherToken)->sole();
    $this->get(route('forms.preview.show', [$this->exam->slug, $teacherSession->id]))->assertNotFound();

    auth()->logout();
    ($this->closePreview)($teacherToken)->assertUnauthorized();
    expect($teacherSession->refresh()->preview_closed_at)->toBeNull();
});

test('a preview left open long ago is over: opening it again starts a new one', function () {
    Carbon::setTestNow('2026-11-20 01:00:00');
    $this->actingAs($this->teacher);
    $token = ($this->openPreview)()->inertiaProps('session.token');

    // Back two hours later (a reload): the same preview goes on.
    Carbon::setTestNow('2026-11-20 03:00:00');
    expect(($this->openPreview)()->inertiaProps('session.token'))->toBe($token);

    Carbon::setTestNow('2026-11-24 01:00:00');
    $page = ($this->openPreview)();
    $left = QuizSession::query()->where('session_token', $token)->sole();

    expect($page->inertiaProps('session.token'))->not->toBe($token)
        ->and($page->inertiaProps('preview.openedAt'))->toBe(now()->toISOString())
        ->and($left->preview_closed_at?->lessThanOrEqualTo(Carbon::parse('2026-11-20 03:00:00')))->toBeTrue()
        ->and($left->submitted_at)->not->toBeNull()
        // Not closed with the button: nothing in Laporan Aktivitas.
        ->and(ActivityLog::query()->where('event', ActivityLog::PREVIEW_CLOSED)->exists())->toBeFalse();

    // A student's attempt is never ended this way.
    Carbon::setTestNow('2026-11-20 01:00:00');
    $student = User::factory()->create(['role' => 'siswa']);
    $studentToken = $this->actingAs($student)->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');
    Carbon::setTestNow('2026-11-24 01:00:00');
    expect($this->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token'))->toBe($studentToken);
});

test('before the database is migrated, a preview left open long ago still ends instead of failing', function () {
    Carbon::setTestNow('2026-11-20 01:00:00');
    $this->actingAs($this->teacher);
    $token = ($this->openPreview)()->inertiaProps('session.token');
    Schema::table('quiz_sessions', fn (Blueprint $table) => $table->dropColumn('preview_closed_at'));

    Carbon::setTestNow('2026-11-24 01:00:00');
    $page = ($this->openPreview)()->assertOk();

    expect($page->inertiaProps('session.token'))->not->toBe($token)
        ->and(QuizSession::query()->where('session_token', $token)->value('submitted_at'))->not->toBeNull();
});

test('a duration reads as people say it', function (int $seconds, string $label) {
    expect(DisplayTime::duration($seconds))->toBe($label);
})->with([
    [0, '0 detik'],
    [45, '45 detik'],
    [120, '2 menit'],
    [1057, '17 menit 37 detik'],
    [3600, '1 jam'],
    [3905, '1 jam 5 menit'],
]);
