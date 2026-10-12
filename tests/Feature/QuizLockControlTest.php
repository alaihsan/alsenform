<?php

use App\Actions\SubmitExpiredAttempts;
use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\UnlockRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $settings
 */
function lockingQuiz(array $settings = [], ?User $owner = null): QuizForm
{
    return QuizForm::factory()->create([
        'user_id' => ($owner ?? User::factory()->create(['role' => 'guru']))->id,
        'published_at' => now(),
        'settings' => array_merge(['collectEmail' => false, 'showProgress' => true, 'shuffleQuestions' => false, 'lockOnBlur' => true], $settings),
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function runningAttempt(QuizForm $quizForm, array $attributes = []): QuizSession
{
    return QuizSession::create(array_merge([
        'quiz_form_id' => $quizForm->id,
        'respondent_identifier' => 'anon_'.Str::random(16),
        'respondent_name' => 'Budi Santoso',
        'respondent_class' => '8B',
        'session_token' => Str::random(40),
        'started_at' => now(),
        'is_locked' => false,
    ], $attributes));
}

function lockAttempt(QuizForm $quizForm, QuizSession $session): void
{
    test()->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token])
        ->assertOk()
        ->assertJsonPath('locked', true);
}

/**
 * @return array<string, mixed>
 */
function submission(QuizSession $session, bool $isTimeout = false): array
{
    return [
        'respondent_identifier' => $session->respondent_identifier,
        'session_token' => $session->session_token,
        'answers' => [1 => 'Option 2'],
        'is_timeout' => $isTimeout,
    ];
}

test('a student who leaves the exam tab is locked and the page sees it in the status', function () {
    $quizForm = lockingQuiz();
    $session = runningAttempt($quizForm);

    lockAttempt($quizForm, $session);

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]))
        ->assertOk()
        ->assertExactJson([
            'lock_on_blur' => true,
            'locked' => true,
            'unlock_requested' => false,
            'submitted' => false,
        ]);

    expect($session->refresh())
        ->is_locked->toBeTrue()
        ->blur_count->toBe(1)
        ->locked_at->not->toBeNull();
});

test('a locked student appears in the teacher list without asking and can continue once unlocked', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $quizForm = lockingQuiz(owner: $teacher);
    $session = runningAttempt($quizForm);
    $calmStudent = runningAttempt($quizForm, ['respondent_name' => 'Siti']);

    lockAttempt($quizForm, $session);

    $this->postJson(route('forms.responses.store', $quizForm->slug), submission($session))->assertForbidden();

    $list = $this->actingAs($teacher)
        ->getJson(route('forms.unlock-requests.index', $quizForm))
        ->assertOk()
        ->assertJsonPath('locked_count', 1)
        ->assertJsonCount(1, 'requests')
        ->json('requests.0');

    expect($list)
        ->session_id->toBe($session->id)
        ->request_id->toBeNull()
        ->name->toBe('Budi Santoso')
        ->kelas->toBe('8B')
        ->state->toBe('locked')
        ->unlock_requested->toBeFalse()
        ->blur_count->toBe(1);

    $this->actingAs($teacher)
        ->postJson(route('forms.sessions.unlock', [$quizForm, $session]))
        ->assertOk()
        ->assertJsonPath('request.state', 'unlocked');

    expect($session->refresh())->is_locked->toBeFalse()->unlocked_at->not->toBeNull()
        ->and($calmStudent->refresh()->blur_count)->toBe(0);

    auth()->logout();

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]))
        ->assertOk()
        ->assertJsonPath('locked', false);

    $this->postJson(route('forms.responses.store', $quizForm->slug), submission($session))->assertOk();

    expect(QuizResponse::query()->where('quiz_form_id', $quizForm->id)->count())->toBe(1);
});

test('a student asking to be unlocked is marked in the list and the request is closed by the unlock', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $quizForm = lockingQuiz(owner: $teacher);
    $session = runningAttempt($quizForm);

    lockAttempt($quizForm, $session);

    $this->postJson(route('forms.public.unlock-requests.store', $quizForm->slug), [
        'respondent_identifier' => $session->respondent_identifier,
    ])->assertOk();

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]))
        ->assertJsonPath('locked', true)
        ->assertJsonPath('unlock_requested', true);

    $this->actingAs($teacher)
        ->getJson(route('forms.unlock-requests.index', $quizForm))
        ->assertJsonPath('requests.0.unlock_requested', true)
        ->assertJsonPath('requests.0.status', 'pending');

    $this->actingAs($teacher)->postJson(route('forms.sessions.unlock', [$quizForm, $session]))->assertOk();

    expect(UnlockRequest::query()->where('respondent_identifier', $session->respondent_identifier)->value('status'))->toBe('approved');
});

test('a student locked again after an earlier unlock stays locked until the teacher unlocks again', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $quizForm = lockingQuiz(owner: $teacher);
    $session = runningAttempt($quizForm);

    lockAttempt($quizForm, $session);
    $this->postJson(route('forms.public.unlock-requests.store', $quizForm->slug), ['respondent_identifier' => $session->respondent_identifier]);
    $this->actingAs($teacher)->postJson(route('forms.sessions.unlock', [$quizForm, $session]))->assertOk();
    auth()->logout();

    lockAttempt($quizForm, $session);

    // The approved request of the first lock no longer unlocks anything.
    $this->getJson(route('forms.public.unlock-requests.status', [$quizForm->slug, $session->respondent_identifier]))
        ->assertJsonPath('status', 'approved');
    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]))
        ->assertJsonPath('locked', true)
        ->assertJsonPath('unlock_requested', false);

    expect($session->refresh())->is_locked->toBeTrue()->blur_count->toBe(2);

    $this->actingAs($teacher)
        ->getJson(route('forms.unlock-requests.index', $quizForm))
        ->assertJsonPath('requests.0.state', 'locked');
});

test('nothing is locked while the teacher has the lock switched off', function () {
    $quizForm = lockingQuiz(['lockOnBlur' => false]);
    $session = runningAttempt($quizForm);

    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token])
        ->assertOk()
        ->assertJsonPath('lock_on_blur', false)
        ->assertJsonPath('locked', false);

    expect($session->refresh())->is_locked->toBeFalse()->blur_count->toBe(0);
});

test('switching the lock on during the exam reaches students who are already working', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $quizForm = lockingQuiz(['lockOnBlur' => false], $teacher);
    $session = runningAttempt($quizForm);
    $statusUrl = route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]);

    $this->getJson($statusUrl)->assertJsonPath('lock_on_blur', false);

    $this->actingAs($teacher)->patch(route('forms.update', $quizForm), [
        'title' => $quizForm->title,
        'slug' => $quizForm->slug,
        'questions' => $quizForm->questions,
        'settings' => array_merge($quizForm->settings, ['lockOnBlur' => true]),
        'published' => true,
    ])->assertRedirect();
    auth()->logout();

    $this->getJson($statusUrl)->assertJsonPath('lock_on_blur', true)->assertJsonPath('locked', false);

    lockAttempt($quizForm, $session);
});

test('switching the lock off during the exam releases every locked student', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $quizForm = lockingQuiz(owner: $teacher);
    $locked = runningAttempt($quizForm);
    $asked = runningAttempt($quizForm);

    lockAttempt($quizForm, $locked);
    lockAttempt($quizForm, $asked);
    $this->postJson(route('forms.public.unlock-requests.store', $quizForm->slug), ['respondent_identifier' => $asked->respondent_identifier]);

    $this->actingAs($teacher)->patch(route('forms.update', $quizForm), [
        'title' => $quizForm->title,
        'slug' => $quizForm->slug,
        'questions' => $quizForm->questions,
        'settings' => array_merge($quizForm->settings, ['lockOnBlur' => false]),
        'published' => true,
    ])->assertRedirect();
    auth()->logout();

    expect($locked->refresh()->is_locked)->toBeFalse()
        ->and($asked->refresh()->is_locked)->toBeFalse()
        ->and(UnlockRequest::query()->where('respondent_identifier', $asked->respondent_identifier)->value('status'))->toBe('approved');

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $locked->session_token]))
        ->assertJsonPath('lock_on_blur', false)
        ->assertJsonPath('locked', false);

    $this->postJson(route('forms.responses.store', $quizForm->slug), submission($locked))->assertOk();
});

test('an attempt locked while the lock was on is not locked once it is switched off', function () {
    $quizForm = lockingQuiz(['lockOnBlur' => false]);
    $session = runningAttempt($quizForm, ['is_locked' => true]);

    $this->get(route('forms.public', $quizForm->slug))->assertOk();

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]))
        ->assertJsonPath('locked', false);

    $this->postJson(route('forms.responses.store', $quizForm->slug), submission($session))->assertOk();
});

test('the exam page receives the lock state of the attempt', function () {
    $student = User::factory()->create(['role' => 'siswa']);
    $quizForm = lockingQuiz();
    runningAttempt($quizForm, ['user_id' => $student->id, 'respondent_identifier' => 'user_'.$student->id, 'is_locked' => true]);

    $this->actingAs($student)
        ->get(route('forms.public', $quizForm->slug))
        ->assertInertia(fn (Assert $page) => $page->where('session.is_locked', true));

    $quizForm->update(['settings' => array_merge($quizForm->settings, ['lockOnBlur' => false])]);

    $this->actingAs($student)
        ->get(route('forms.public', $quizForm->slug))
        ->assertInertia(fn (Assert $page) => $page->where('session.is_locked', false));
});

test('a locked student hands in the answers when the time is up', function () {
    $quizForm = lockingQuiz(['timeLimit' => 10]);
    $session = runningAttempt($quizForm, ['started_at' => now()->subMinutes(10), 'expires_at' => now()->addSeconds(5)]);

    lockAttempt($quizForm, $session);

    $this->postJson(route('forms.responses.store', $quizForm->slug), submission($session, isTimeout: true))->assertOk();

    expect(QuizResponse::query()->where('quiz_form_id', $quizForm->id)->sole())
        ->is_timeout->toBeTrue()
        ->respondent_name->toBe('Budi Santoso')
        ->and(ActivityLog::query()->where('event', ActivityLog::EXAM_SUBMITTED)->sole()->meta)->toEqual(['timeout' => true, 'locked' => true]);

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]))
        ->assertJsonPath('locked', false)
        ->assertJsonPath('submitted', true);
});

test('a locked student cannot hand in early by claiming the time is up', function () {
    $quizForm = lockingQuiz(['timeLimit' => 10]);
    $session = runningAttempt($quizForm, ['expires_at' => now()->addMinutes(8)]);

    lockAttempt($quizForm, $session);

    $this->postJson(route('forms.responses.store', $quizForm->slug), submission($session, isTimeout: true))->assertForbidden();

    expect(QuizResponse::query()->where('quiz_form_id', $quizForm->id)->exists())->toBeFalse();
});

test('the teacher can unlock every locked student at once', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $quizForm = lockingQuiz(owner: $teacher);
    $sessions = collect(range(1, 3))->map(fn () => runningAttempt($quizForm));
    $sessions->each(fn (QuizSession $session) => lockAttempt($quizForm, $session));

    $this->actingAs($teacher)
        ->postJson(route('forms.sessions.unlock-all', $quizForm))
        ->assertOk()
        ->assertJsonPath('unlocked', 3);

    $sessions->each(fn (QuizSession $session) => expect($session->refresh()->is_locked)->toBeFalse());

    $this->actingAs($teacher)
        ->getJson(route('forms.unlock-requests.index', $quizForm))
        ->assertJsonPath('locked_count', 0)
        ->assertJsonCount(3, 'requests')
        ->assertJsonPath('requests.0.state', 'unlocked');
});

test('only editors of the form can unlock its students', function () {
    $quizForm = lockingQuiz();
    $otherForm = lockingQuiz();
    $session = runningAttempt($quizForm, ['is_locked' => true]);
    $stranger = User::factory()->create(['role' => 'guru']);

    $this->postJson(route('forms.sessions.unlock', [$quizForm, $session]))->assertUnauthorized();
    $this->actingAs($stranger)->postJson(route('forms.sessions.unlock', [$quizForm, $session]))->assertForbidden();
    $this->actingAs($stranger)->postJson(route('forms.sessions.unlock-all', $quizForm))->assertForbidden();
    $this->actingAs($otherForm->user)->postJson(route('forms.sessions.unlock', [$otherForm, $session]))->assertNotFound();

    expect($session->refresh()->is_locked)->toBeTrue();
});

test('a student cannot lock the attempt of somebody else', function () {
    $quizForm = lockingQuiz();
    $owner = User::factory()->create(['role' => 'siswa']);
    $classmate = User::factory()->create(['role' => 'siswa']);
    $session = runningAttempt($quizForm, ['user_id' => $owner->id, 'respondent_identifier' => 'user_'.$owner->id]);

    $this->actingAs($classmate)
        ->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token])
        ->assertForbidden();

    auth()->logout();

    // A guest naming the account's identifier does not reach the account's attempt either.
    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['respondent_identifier' => 'user_'.$owner->id])
        ->assertOk()
        ->assertJsonPath('locked', false);

    expect($session->refresh()->is_locked)->toBeFalse();
});

test('an attempt is not locked before the exam starts or after it is handed in', function () {
    $quizForm = lockingQuiz();
    $notStarted = runningAttempt($quizForm, ['started_at' => null]);
    $handedIn = runningAttempt($quizForm, ['submitted_at' => now()]);

    foreach ([$notStarted, $handedIn] as $session) {
        $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token])
            ->assertOk()
            ->assertJsonPath('locked', false);

        expect($session->refresh()->is_locked)->toBeFalse();
    }
});

test('the lock status needs the token of an existing attempt', function () {
    $quizForm = lockingQuiz();

    $this->getJson(route('forms.responses.status', $quizForm->slug))->assertUnprocessable();
    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => 'unknown']))->assertNotFound();
});

test('the answers on the page when the student left are the ones that count while locked', function () {
    $quizForm = lockingQuiz(['timeLimit' => 10]);
    $session = runningAttempt($quizForm, ['expires_at' => now()->addMinutes(5)]);

    $this->postJson(route('forms.responses.lock', $quizForm->slug), [
        'session_token' => $session->session_token,
        'answers' => [1 => 'Option 1'],
    ])->assertJsonPath('locked', true);

    // Changes made behind the lock screen (developer tools) are not saved...
    $this->postJson(route('forms.responses.draft', $quizForm->slug), [
        'session_token' => $session->session_token,
        'answers' => [1 => 'Option 2'],
    ])->assertOk()->assertJsonPath('saved', false)->assertJsonPath('locked', true);

    // ...a second lock request does not replace the copy taken at the first one...
    $this->postJson(route('forms.responses.lock', $quizForm->slug), [
        'session_token' => $session->session_token,
        'answers' => [1 => 'Option 2'],
    ])->assertJsonPath('locked', true);

    expect($session->refresh()->draft_answers)->toEqual([1 => 'Option 1']);

    // ...and do not count when the time runs out while still locked.
    $this->travel(5)->minutes();

    $this->postJson(route('forms.responses.store', $quizForm->slug), array_merge(submission($session, isTimeout: true), [
        'answers' => [1 => 'Option 2'],
    ]))->assertOk();

    expect(QuizResponse::query()->sole()->answers)->toEqual([1 => 'Option 1']);
});

test('after the teacher unlocks, the student answers and saves as usual', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $quizForm = lockingQuiz(owner: $teacher);
    $session = runningAttempt($quizForm);

    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token, 'answers' => [1 => 'Option 1']]);
    $this->actingAs($teacher)->postJson(route('forms.sessions.unlock', [$quizForm, $session]))->assertOk();
    auth()->logout();

    $this->postJson(route('forms.responses.draft', $quizForm->slug), [
        'session_token' => $session->session_token,
        'answers' => [1 => 'Option 2'],
    ])->assertJsonPath('saved', true);

    $this->postJson(route('forms.responses.store', $quizForm->slug), array_merge(submission($session), ['answers' => [1 => 'Option 2']]))->assertOk();

    expect(QuizResponse::query()->sole()->answers)->toEqual([1 => 'Option 2']);
});

test('a lock with answers to unknown questions still locks, without keeping those answers', function () {
    $quizForm = lockingQuiz();
    $session = runningAttempt($quizForm, ['draft_answers' => [1 => 'Option 1']]);

    $this->postJson(route('forms.responses.lock', $quizForm->slug), [
        'session_token' => $session->session_token,
        'answers' => [999 => 'injected'],
    ])->assertJsonPath('locked', true);

    expect($session->refresh())
        ->is_locked->toBeTrue()
        ->draft_answers->toEqual([1 => 'Option 1']);
});

test('the page asking while the lock is off changes nothing, and is locked the moment the teacher switches it on', function () {
    $quizForm = lockingQuiz(['lockOnBlur' => false]);
    $session = runningAttempt($quizForm);
    $leave = fn () => $this->postJson(route('forms.responses.lock', $quizForm->slug), [
        'session_token' => $session->session_token,
        'answers' => [1 => 'Option 1'],
    ]);

    $leave()->assertExactJson(['lock_on_blur' => false, 'locked' => false, 'unlock_requested' => false, 'submitted' => false]);
    expect($session->refresh())->blur_count->toBe(0)->draft_answers->toBeNull();

    $quizForm->update(['settings' => array_merge($quizForm->settings, ['lockOnBlur' => true])]);

    $leave()->assertJsonPath('locked', true)->assertJsonPath('lock_on_blur', true);
    expect($session->refresh())->blur_count->toBe(1)->draft_answers->toEqual([1 => 'Option 1']);
});

test('answers handed in by the server for a locked attempt are not changed by the page later', function () {
    $quizForm = lockingQuiz(['timeLimit' => 10]);
    $session = runningAttempt($quizForm, ['expires_at' => now()->addMinute()]);
    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token, 'answers' => [1 => 'Option 1']]);

    $this->travel(3)->minutes();
    app(SubmitExpiredAttempts::class)->handle();

    $this->postJson(route('forms.responses.store', $quizForm->slug), array_merge(submission($session, isTimeout: true), [
        'answers' => [1 => 'Option 2'],
    ]))->assertOk();

    expect(QuizResponse::query()->sole())
        ->answers->toEqual([1 => 'Option 1'])
        ->is_auto_submitted->toBeTrue();
});

test('reloading the exam page is not leaving the exam, being away longer is', function () {
    $student = User::factory()->create(['role' => 'siswa']);
    $quizForm = lockingQuiz();
    $session = runningAttempt($quizForm, ['user_id' => $student->id, 'respondent_identifier' => 'user_'.$student->id]);
    $unload = fn () => $this->actingAs($student)->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token, 'reason' => 'unload']);

    // The page is reloaded: noted, not locked, and back within seconds.
    $unload()->assertJsonPath('locked', false);
    expect($session->refresh()->left_at)->not->toBeNull()->and($session->is_locked)->toBeFalse();

    $this->travel(5)->seconds();
    $this->actingAs($student)->get(route('forms.public', $quizForm->slug))->assertInertia(fn (Assert $page) => $page->where('session.is_locked', false));
    expect($session->refresh()->left_at)->not->toBeNull();

    // The page that loaded tells it is a reload of the same tab.
    $this->actingAs($student)->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token, 'returning' => 'reload']))
        ->assertJsonPath('locked', false);
    expect($session->refresh())->left_at->toBeNull()->blur_count->toBe(0);

    // The page does not come back in time (closed, or another site in the same tab).
    $unload();
    $this->travel(25)->seconds();
    $this->actingAs($student)->get(route('forms.public', $quizForm->slug))->assertInertia(fn (Assert $page) => $page->where('session.is_locked', true));
    expect($session->refresh())->is_locked->toBeTrue()->blur_count->toBe(1)->left_at->toBeNull();
});

test('a page brought back from the browser history after a while is locked by its next status check', function () {
    $quizForm = lockingQuiz();
    $session = runningAttempt($quizForm);

    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token, 'reason' => 'unload']);
    $this->travel(30)->seconds();

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]))
        ->assertJsonPath('locked', true);
});

test('leaving the page while the lock is off is never held against the student', function () {
    $quizForm = lockingQuiz(['lockOnBlur' => false]);
    $session = runningAttempt($quizForm);

    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token, 'reason' => 'unload']);
    $this->travel(30)->seconds();

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]))
        ->assertJsonPath('locked', false);
    expect($session->refresh()->blur_count)->toBe(0);
});

test('closing the exam tab and opening the exam again within seconds is leaving the exam', function () {
    $quizForm = lockingQuiz();
    $session = runningAttempt($quizForm);

    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token, 'reason' => 'unload']);
    $this->travel(5)->seconds();

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token, 'returning' => 'navigate']))
        ->assertJsonPath('locked', true);
    expect($session->refresh())->blur_count->toBe(1)->left_at->toBeNull();
});

test('another site in the same tab and back within seconds is leaving the exam', function () {
    $quizForm = lockingQuiz();
    $session = runningAttempt($quizForm);

    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token, 'reason' => 'unload']);
    $this->travel(3)->seconds();

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token, 'returning' => 'back_forward']))
        ->assertJsonPath('locked', true);
});

test('a reload is only settled by the page itself, unless it stays away too long', function () {
    $quizForm = lockingQuiz();
    $session = runningAttempt($quizForm);
    $status = fn () => $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]));

    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token, 'reason' => 'unload']);
    $this->travel(5)->seconds();

    // Another request in the meantime neither clears nor locks it.
    $status()->assertJsonPath('locked', false);
    expect($session->refresh()->left_at)->not->toBeNull();

    $this->travel(20)->seconds();
    $status()->assertJsonPath('locked', true);
});

test('an attempt the teacher unlocked is not locked again by an earlier leave', function () {
    $quizForm = lockingQuiz();
    $session = runningAttempt($quizForm);

    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token, 'reason' => 'unload']);
    $this->postJson(route('forms.responses.lock', $quizForm->slug), ['session_token' => $session->session_token])->assertJsonPath('locked', true);
    expect($session->refresh()->left_at)->toBeNull();

    $session->unlock();
    $this->travel(30)->seconds();

    $this->getJson(route('forms.responses.status', ['quizForm' => $quizForm->slug, 'session_token' => $session->session_token]))
        ->assertJsonPath('locked', false);
    expect($session->refresh()->blur_count)->toBe(1);
});
