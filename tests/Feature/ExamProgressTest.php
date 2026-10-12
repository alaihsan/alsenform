<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function progressExam(User $teacher): QuizForm
{
    return QuizForm::factory()->create([
        'user_id' => $teacher->id,
        'published_at' => now(),
        'settings' => ['collectEmail' => false, 'showProgress' => true, 'timeLimit' => 30],
    ]);
}

test('the teacher sees how many students handed in, each counted once', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $quizForm = progressExam($teacher);
    [$budi, $sari] = User::factory()->count(2)->create(['role' => 'siswa']);

    // Budi handed in twice (two attempts), Sari once, and two guests on their own devices.
    QuizResponse::factory()->for($quizForm)->count(2)->create(['user_id' => $budi->id, 'answers' => []]);
    QuizResponse::factory()->for($quizForm)->create(['user_id' => $sari->id, 'answers' => []]);
    QuizResponse::factory()->for($quizForm)->create(['user_id' => null, 'respondent_identifier' => 'anon_a', 'answers' => []]);
    QuizResponse::factory()->for($quizForm)->create(['user_id' => null, 'respondent_identifier' => 'anon_b', 'answers' => []]);

    // Working now: one page in touch a moment ago; one attempt left long ago does not count.
    QuizSession::create(['quiz_form_id' => $quizForm->id, 'respondent_identifier' => 'anon_c', 'session_token' => Str::random(40), 'started_at' => now()->subMinutes(5), 'last_seen_at' => now()->subSeconds(20)]);
    QuizSession::create(['quiz_form_id' => $quizForm->id, 'respondent_identifier' => 'anon_d', 'session_token' => Str::random(40), 'started_at' => now()->subMinutes(5), 'last_seen_at' => now()->subMinutes(4)]);

    $this->actingAs($teacher)->getJson(route('forms.progress', $quizForm))
        ->assertOk()
        ->assertExactJson(['submitted' => 4, 'responses' => 5, 'working' => 1]);

    $this->actingAs($teacher)->get(route('forms.edit', $quizForm))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.students', 4)
            ->where('quizForm.responses.total', 5)
            ->where('quizForm.responses.progressUrl', route('forms.progress', $quizForm)));
});

test('an attempt whose time ran out with the page closed counts as handed in', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $quizForm = progressExam($teacher);
    QuizSession::create([
        'quiz_form_id' => $quizForm->id,
        'respondent_identifier' => 'anon_late',
        'session_token' => Str::random(40),
        'started_at' => now()->subMinutes(40),
        'expires_at' => now()->subMinutes(10),
        'draft_answers' => [1 => 'Option 1'],
        'draft_saved_at' => now()->subMinutes(11),
    ]);

    $this->actingAs($teacher)->getJson(route('forms.progress', $quizForm))
        ->assertJsonPath('submitted', 1)
        ->assertJsonPath('working', 0);
});

test('only the teachers of the exam see its progress', function () {
    $quizForm = progressExam(User::factory()->create(['role' => 'guru']));

    $this->actingAs(User::factory()->create(['role' => 'guru']))->getJson(route('forms.progress', $quizForm))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'siswa']))->getJson(route('forms.progress', $quizForm))->assertForbidden();
});
