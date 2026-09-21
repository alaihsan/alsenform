<?php

use App\Models\Cohort;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\UnlockRequest;
use App\Models\User;
use App\Support\UnlockCode;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

// ---------------------------------------------------------------------------
// Issue 1: Role escalation and user management authorization
// ---------------------------------------------------------------------------
test('issue 1: non-admin users cannot access user management or elevate roles', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'is_admin' => false]);
    $student = User::factory()->create(['role' => 'siswa', 'is_admin' => false]);
    $targetUser = User::factory()->create(['role' => 'guru', 'is_admin' => false]);

    // Teacher cannot access user management
    $this->actingAs($teacher)->get(route('users.index'))->assertStatus(403);
    $this->actingAs($teacher)->patch(route('users.update-role', $targetUser), ['role' => 'admin'])->assertStatus(403);
    $this->actingAs($teacher)->post(route('users.store'), [
        'name' => 'New Admin',
        'email' => 'newadmin@test.com',
        'password' => 'password123',
        'role' => 'admin',
    ])->assertStatus(403);

    // Student cannot access user management
    $this->actingAs($student)->get(route('users.index'))->assertStatus(403);
    $this->actingAs($student)->patch(route('users.update-role', $student), ['role' => 'admin'])->assertStatus(403);

    // Admin cannot change own role / self-demote
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->actingAs($admin)->patch(route('users.update-role', $admin), ['role' => 'siswa'])
        ->assertSessionHasErrors(['error']);
});

// ---------------------------------------------------------------------------
// Issue 2: Answer key leakage in public quiz payload
// ---------------------------------------------------------------------------
test('issue 2: answer keys are stripped from public quiz inertia payload', function () {
    $form = QuizForm::factory()->create([
        'slug' => 'test-quiz-leak-check',
        'published_at' => now(),
        'questions' => [
            [
                'id' => 101,
                'title' => 'Secret Question 1',
                'type' => 'Multiple choice',
                'options' => ['Option A', 'Option B'],
                'answer' => 1,
                'required' => true,
                'points' => 10,
            ],
            [
                'id' => 102,
                'title' => 'Secret Question 2',
                'type' => 'Short answer',
                'answer' => 'SuperSecretKey',
                'required' => false,
                'points' => 5,
            ],
        ],
    ]);

    $response = $this->get(route('forms.public', $form->slug));
    $response->assertSuccessful();

    $response->assertInertia(function (Assert $page) {
        $page->component('PublicQuiz')
            ->has('quizForm.questions', 2)
            ->where('quizForm.questions.0.id', 101)
            ->missing('quizForm.questions.0.answer')
            ->where('quizForm.questions.1.id', 102)
            ->missing('quizForm.questions.1.answer');
    });
});

// ---------------------------------------------------------------------------
// Issue 3: Self-approval via leaked unlock code prevented
// ---------------------------------------------------------------------------
test('issue 3: unlock code is not leaked in json and requires authorized approval', function () {
    $owner = User::factory()->create(['role' => 'guru']);
    $otherTeacher = User::factory()->create(['role' => 'guru']);
    $form = QuizForm::factory()->create([
        'user_id' => $owner->id,
        'published_at' => now(),
    ]);

    // 1. Respondent requests unlock - code MUST NOT be in response JSON
    $response = $this->postJson(route('forms.public.unlock-requests.store', $form->slug), [
        'respondent_identifier' => 'student-device-xyz',
        'email' => 'student@test.com',
    ]);
    $response->assertStatus(200);
    $response->assertJsonMissing(['code']);

    $req = UnlockRequest::where('quiz_form_id', $form->id)
        ->where('respondent_identifier', 'student-device-xyz')
        ->first();
    expect($req)->not->toBeNull();
    expect($req->status)->toBe('pending');

    // 2. Unauthorized teacher cannot approve
    $this->actingAs($otherTeacher)
        ->postJson(route('forms.unlock-requests.approve', $req))
        ->assertStatus(403);

    // 3. Form owner can approve
    $this->actingAs($owner)
        ->postJson(route('forms.unlock-requests.approve', $req))
        ->assertStatus(200);
    expect($req->refresh()->status)->toBe('approved');

    // 4. Code verification marks request used and cannot be reused
    $req->update([
        'unlock_code' => app(UnlockCode::class)->hash('654321'),
        'status' => 'pending',
    ]);

    $this->postJson(route('forms.public.unlock-verify', $form->slug), [
        'respondent_identifier' => 'student-device-xyz',
        'code' => '654321',
    ])->assertStatus(200)->assertJsonPath('success', true);

    expect($req->refresh()->status)->toBe('used');

    // Reusing the same code fails
    $this->postJson(route('forms.public.unlock-verify', $form->slug), [
        'respondent_identifier' => 'student-device-xyz',
        'code' => '654321',
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Issue 4: Cohort membership modification authorization
// ---------------------------------------------------------------------------
test('issue 4: non-creator teachers and students cannot modify cohort membership', function () {
    $creator = User::factory()->create(['role' => 'guru']);
    $intruderTeacher = User::factory()->create(['role' => 'guru']);
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '8899001122']);
    $studentToAdd = User::factory()->create(['role' => 'siswa', 'nis' => '8899001133']);

    $cohort = Cohort::create([
        'name' => 'Cohort Biologi',
        'code' => 'BIO-01',
        'created_by' => $creator->id,
    ]);

    // Student cannot access cohorts at all
    $this->actingAs($student)->get(route('cohorts.index'))->assertStatus(403);
    $this->actingAs($student)->post(route('cohorts.members.add', $cohort), [
        'user_ids' => [$student->id],
    ])->assertStatus(403);

    // Non-creator teacher cannot add or remove members
    $this->actingAs($intruderTeacher)->post(route('cohorts.members.add', $cohort), [
        'user_ids' => [$studentToAdd->id],
    ])->assertStatus(403);

    // Creator teacher can add members
    $this->actingAs($creator)->post(route('cohorts.members.add', $cohort), [
        'user_ids' => [$studentToAdd->id],
    ])->assertRedirect();
    expect($cohort->users()->where('users.id', $studentToAdd->id)->exists())->toBeTrue();

    // Intruder teacher cannot remove member
    $this->actingAs($intruderTeacher)->delete(route('cohorts.members.remove', [$cohort, $studentToAdd]))
        ->assertStatus(403);

    // Creator teacher can remove member
    $this->actingAs($creator)->delete(route('cohorts.members.remove', [$cohort, $studentToAdd]))
        ->assertRedirect();
    expect($cohort->users()->where('users.id', $studentToAdd->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Issue 5: Server-enforced time limit and session lock
// ---------------------------------------------------------------------------
test('issue 5: server enforces time limit and locks submission when session is locked', function () {
    $form = QuizForm::factory()->create([
        'slug' => 'timed-quiz',
        'published_at' => now(),
        'settings' => [
            'timeLimit' => 10,
            'lockOnBlur' => true,
        ],
        'questions' => [
            ['id' => 1, 'title' => 'Q1', 'type' => 'Short answer', 'required' => false],
        ],
    ]);

    $respondentId = 'device-locked-check';

    // 1. Visit show to create session
    $this->get(route('forms.public', $form->slug))->assertSuccessful();

    // 2. Lock the session
    $session = QuizSession::create([
        'quiz_form_id' => $form->id,
        'respondent_identifier' => $respondentId,
        'started_at' => now(),
        'expires_at' => now()->addMinutes(10),
        'is_locked' => true,
        'session_token' => Str::random(40),
    ]);

    // Attempt to submit while session is locked must return 403
    $this->post(route('forms.responses.store', $form->slug), [
        'respondent_identifier' => $respondentId,
        'answers' => [1 => 'answer'],
    ])->assertStatus(403);
});

// ---------------------------------------------------------------------------
// Issue 6: Answer validation, participant user_id, single-response limit
// ---------------------------------------------------------------------------
test('issue 6: binds user_id, calculates score on server, and enforces single-response limit', function () {
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '1122334455']);

    $form = QuizForm::factory()->create([
        'slug' => 'single-response-quiz',
        'published_at' => now(),
        'settings' => [
            'limitOneResponse' => true,
        ],
        'questions' => [
            [
                'id' => 1,
                'title' => 'Capital of France',
                'type' => 'Multiple choice',
                'options' => ['London', 'Paris', 'Berlin'],
                'answer' => 1, // index 1 = Paris
                'points' => 20,
                'required' => true,
            ],
        ],
    ]);

    // 1. Submit with invalid question ID rejected
    $this->actingAs($student)->post(route('forms.responses.store', $form->slug), [
        'answers' => [999 => 'Hacked Option'],
    ])->assertSessionHasErrors(['answers.999']);

    // 2. Valid first submission succeeds and records score and user_id
    $this->actingAs($student)->post(route('forms.responses.store', $form->slug), [
        'answers' => [1 => 'Paris'],
    ])->assertRedirect(route('forms.public', $form->slug));

    $response = QuizResponse::where('quiz_form_id', $form->id)->where('user_id', $student->id)->first();
    expect($response)->not->toBeNull();
    expect($response->score)->toBe(20);
    expect($response->user_id)->toBe($student->id);

    // 3. Second submission is blocked by limitOneResponse
    $this->actingAs($student)->post(route('forms.responses.store', $form->slug), [
        'answers' => [1 => 'Paris'],
    ])->assertSessionHasErrors(['error']);
});

// ---------------------------------------------------------------------------
// Issue 7: Graceful partial submit on timeout
// ---------------------------------------------------------------------------
test('issue 7: timeout allows partial submission even with unanswered required questions', function () {
    $form = QuizForm::factory()->create([
        'slug' => 'timeout-partial-quiz',
        'published_at' => now(),
        'questions' => [
            [
                'id' => 1,
                'title' => 'Answered Question',
                'type' => 'Short answer',
                'answer' => 'Hello',
                'points' => 10,
                'required' => true,
            ],
            [
                'id' => 2,
                'title' => 'Unanswered Required Question',
                'type' => 'Short answer',
                'answer' => 'World',
                'points' => 10,
                'required' => true,
            ],
        ],
    ]);

    // Submitting normally without Q2 fails required validation
    $this->post(route('forms.responses.store', $form->slug), [
        'answers' => [1 => 'Hello'],
        'is_timeout' => false,
    ])->assertSessionHasErrors(['answers.2']);

    // Submitting with is_timeout = true succeeds as partial submission
    $resp = $this->postJson(route('forms.responses.store', $form->slug), [
        'answers' => [1 => 'Hello'],
        'is_timeout' => true,
    ]);

    $resp->assertStatus(200);
    $saved = QuizResponse::where('quiz_form_id', $form->id)->first();
    expect($saved)->not->toBeNull();
    expect($saved->is_timeout)->toBeTrue();
    expect($saved->score)->toBe(10);
});

// ---------------------------------------------------------------------------
// Issue 8: Password leak removed and mandatory first-login password change
// ---------------------------------------------------------------------------
test('issue 8: student has must_change_password flag and is redirected to settings until updated', function () {
    $teacher = User::factory()->create(['role' => 'guru']);

    // Teacher creates student
    $this->actingAs($teacher)->post(route('students.store'), [
        'nis' => '9988776655',
        'name' => 'Ahmad Murid',
        'kelas' => 'XII-A',
    ])->assertRedirect();

    $student = User::where('nis', '9988776655')->first();
    expect($student)->not->toBeNull();
    expect($student->must_change_password)->toBeTrue();

    // Student visits dashboard or profile -> redirected to change password
    $this->actingAs($student)->get(route('dashboard'))
        ->assertRedirect(route('password.edit'));

    // Student changes password
    $this->actingAs($student)->put(route('password.update'), [
        'current_password' => '776655',
        'password' => 'newSecretPass123!',
        'password_confirmation' => 'newSecretPass123!',
    ])->assertRedirect();

    expect($student->refresh()->must_change_password)->toBeFalse();

    // Student can now access dashboard normally
    $this->actingAs($student)->get(route('dashboard'))
        ->assertSuccessful();
});
