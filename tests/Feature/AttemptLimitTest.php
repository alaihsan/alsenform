<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\ItemAnalysis;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $settings
 */
function attemptsExam(array $settings = []): QuizForm
{
    return QuizForm::factory()->create([
        'user_id' => User::factory()->create(['role' => 'guru'])->id,
        'published_at' => now(),
        'questions' => [
            ['id' => 1, 'title' => 'Ibu kota Indonesia?', 'type' => 'Multiple choice', 'options' => ['Jakarta', 'Bandung', 'Surabaya'], 'answer' => 'Jakarta', 'points' => 1, 'required' => false],
            ['id' => 2, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4', '5'], 'answer' => '4', 'points' => 1, 'required' => false],
        ],
        'settings' => array_merge(['collectEmail' => false, 'timeLimit' => 30], $settings),
    ]);
}

/**
 * Take the exam the way the exam page does: open it, start it, hand it in.
 *
 * @param  array<int, string>  $answers
 */
function takeExam(QuizForm $quizForm, User $student, array $answers): void
{
    $token = null;
    test()->actingAs($student)->get(route('forms.public', $quizForm->slug))
        ->assertInertia(function (Assert $page) use (&$token) {
            $token = $page->toArray()['props']['session']['token'];
        });

    test()->actingAs($student)->postJson(route('forms.responses.start', $quizForm->slug), ['session_token' => $token])->assertOk();
    test()->actingAs($student)->postJson(route('forms.responses.store', $quizForm->slug), [
        'session_token' => $token,
        'answers' => $answers,
    ])->assertOk();
}

test('a student takes the exam as often as allowed, and every attempt keeps its answers and grade', function () {
    $quizForm = attemptsExam(['maxAttempts' => 2]);
    $student = User::factory()->create(['role' => 'siswa']);

    $this->actingAs($student)->get(route('forms.public', $quizForm->slug))
        ->assertInertia(fn (Assert $page) => $page->where('attempts', ['used' => 0, 'max' => 2]));

    takeExam($quizForm, $student, [1 => 'Bandung', 2 => '4']);

    $this->actingAs($student)->get(route('forms.public', $quizForm->slug))
        ->assertInertia(fn (Assert $page) => $page->where('accessRestricted', false)->where('attempts', ['used' => 1, 'max' => 2]));

    takeExam($quizForm, $student, [1 => 'Jakarta', 2 => '4']);

    expect(QuizResponse::query()->orderBy('id')->pluck('answers')->all())
        ->toEqual([[1 => 'Bandung', 2 => '4'], [1 => 'Jakarta', 2 => '4']]);

    // No third attempt: the page says so, and the server refuses one.
    $this->actingAs($student)->get(route('forms.public', $quizForm->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accessRestricted', true)
            ->where('restrictionReason', 'Anda sudah mengerjakan kuis ini 2 kali, sesuai batas kesempatan yang diberikan guru.'));
    $this->actingAs($student)->postJson(route('forms.responses.store', $quizForm->slug), [
        'session_token' => startedAttempt($quizForm, $student)->session_token,
        'answers' => [1 => 'Jakarta'],
    ])->assertForbidden();
    expect(QuizResponse::query()->count())->toBe(2);

    // The teacher sees both attempts with their own grade.
    $this->actingAs($quizForm->user)->get(route('forms.edit', $quizForm))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.students', 1)
            ->where('quizForm.responses.gradebook.0.attempt', 2)
            ->where('quizForm.responses.gradebook.0.attempts', 2)
            ->where('quizForm.responses.gradebook.0.percentage', 100)
            ->where('quizForm.responses.gradebook.1.attempt', 1)
            ->where('quizForm.responses.gradebook.1.percentage', 50)
            ->where('quizForm.responses.analysis.respondents', 1));
});

test('the older one-response switch still allows one attempt, and no limit allows any number', function () {
    $once = attemptsExam(['limitOneResponse' => true]);
    $student = User::factory()->create(['role' => 'siswa']);

    takeExam($once, $student, [1 => 'Jakarta']);
    $this->actingAs($student)->get(route('forms.public', $once->slug))
        ->assertInertia(fn (Assert $page) => $page->where('accessRestricted', true));

    $unlimited = attemptsExam(['maxAttempts' => 0, 'limitOneResponse' => false]);
    foreach (range(1, 3) as $attempt) {
        takeExam($unlimited, $student, [1 => 'Jakarta']);
    }
    expect($unlimited->responses()->count())->toBe(3);
});

test('starting another attempt is refused once the attempts are used up', function () {
    $quizForm = attemptsExam(['maxAttempts' => 1]);
    $student = User::factory()->create(['role' => 'siswa']);
    takeExam($quizForm, $student, [1 => 'Jakarta']);

    $session = startedAttempt($quizForm, $student, ['started_at' => null]);

    $this->actingAs($student)->postJson(route('forms.responses.start', $quizForm->slug), ['session_token' => $session->session_token])
        ->assertForbidden();
});

test('the item analysis counts the first attempt of each student', function () {
    $quizForm = attemptsExam(['maxAttempts' => 3]);
    $student = User::factory()->create(['role' => 'siswa']);

    takeExam($quizForm, $student, [1 => 'Bandung', 2 => '3']);
    takeExam($quizForm, $student, [1 => 'Jakarta', 2 => '4']);

    $analysis = app(ItemAnalysis::class)->analyze($quizForm->refresh());

    expect($analysis['respondents'])->toBe(1)
        ->and($analysis['questions'][0]['correct'])->toBe(0);
});

test('the attempt limit is saved with the exam settings', function () {
    $quizForm = attemptsExam();

    $this->actingAs($quizForm->user)->patch(route('forms.update', $quizForm), [
        'title' => $quizForm->title,
        'slug' => $quizForm->slug,
        'questions' => $quizForm->questions,
        'settings' => array_merge($quizForm->settings, ['maxAttempts' => 3]),
    ])->assertSessionHasNoErrors();

    expect($quizForm->refresh()->maxAttempts())->toBe(3);

    $this->actingAs($quizForm->user)->patch(route('forms.update', $quizForm), [
        'title' => $quizForm->title,
        'slug' => $quizForm->slug,
        'questions' => $quizForm->questions,
        'settings' => array_merge($quizForm->settings, ['maxAttempts' => -1]),
    ])->assertSessionHasErrors('settings.maxAttempts');
});
