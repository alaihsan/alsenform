<?php

use App\Actions\SubmitExpiredAttempts;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-10-20 00:30:00'); // 07.30 in Jakarta
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 90, 'schedule' => ['opensAt' => '2026-10-20T01:00:00Z', 'closesAt' => '2026-10-20T02:00:00Z']],
        'questions' => [['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4'], 'answer' => '4', 'points' => 1]],
    ]);
    $this->student = User::factory()->create(['role' => 'siswa']);
});

test('before it opens the student waits on a countdown, and cannot start', function () {
    $this->actingAs($this->student)->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accessRestricted', true)
            ->where('restrictionTitle', 'Ujian Belum Dibuka')
            ->where('restrictionReason', 'Ujian ini dibuka Selasa, 20 Oktober 2026 pukul 08.00.')
            ->where('opensAt', '2026-10-20T01:00:00.000000Z'));

    expect(QuizSession::query()->count())->toBe(0);

    $card = collect($this->actingAs($this->student)->get(route('dashboard'))->inertiaProps('recentForms'))->firstWhere('slug', $this->exam->slug);
    expect($card['scheduleLabel'])->toBe('Dibuka Sel, 20 Okt 08.00');
});

test('once open the student starts, and the time runs out at the closing time', function () {
    Carbon::setTestNow('2026-10-20 01:20:00');
    $this->actingAs($this->student);
    $token = $this->get(route('forms.public', $this->exam->slug))->assertInertia(fn (Assert $page) => $page->where('accessRestricted', false))->inertiaProps('session.token');

    // 90 minutes from now would be 02:50: the exam closes at 02:00.
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token])
        ->assertOk()
        ->assertJsonPath('session.expires_at', '2026-10-20T02:00:00.000000Z');

    // At the closing time a student still working is handed in by the server.
    Carbon::setTestNow('2026-10-20 02:02:00');
    expect(app(SubmitExpiredAttempts::class)->handle())->toBe(1)
        ->and(QuizResponse::query()->sole()->is_auto_submitted)->toBeTrue();
});

test('after it closes nobody starts, but the exam\'s teacher can still try it', function () {
    Carbon::setTestNow('2026-10-20 02:00:00');

    $this->actingAs($this->student)->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('restrictionTitle', 'Ujian Sudah Ditutup')->where('opensAt', null));

    $this->actingAs($this->teacher);
    $token = $this->get(route('forms.public', $this->exam->slug))->assertInertia(fn (Assert $page) => $page->where('accessRestricted', false))->inertiaProps('session.token');
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token])->assertOk();
});

test('a session opened before the start time cannot be started early', function () {
    Carbon::setTestNow('2026-10-20 00:59:00');
    $session = QuizSession::query()->create([
        'quiz_form_id' => $this->exam->id, 'user_id' => $this->student->id, 'session_token' => 'token-awal', 'respondent_identifier' => 'user_'.$this->student->id,
    ]);

    $this->actingAs($this->student)->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session->session_token])
        ->assertForbidden()
        ->assertJsonPath('message', 'Ujian belum dibuka. Ujian dibuka Selasa, 20 Oktober 2026 pukul 08.00.');
});
