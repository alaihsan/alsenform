<?php

use App\Actions\SubmitExpiredAttempts;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use App\Support\SectionFlow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A placement test, like a Google Form with "Buka bagian berdasarkan jawaban": the first section
 * asks for the student's major, which sends IPA students to section 10 and IPS students to
 * section 20; "Belum tahu" submits right away. Both majors end the form after their section.
 */
beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'shuffleQuestions' => false],
        'questions' => [
            ['id' => 1, 'title' => 'Jurusan', 'type' => 'Multiple choice', 'options' => ['IPA', 'IPS', 'Belum tahu'], 'points' => 0, 'required' => true,
                'branching' => true, 'optionGoTo' => [10, 20, 'submit']],
            ['id' => 2, 'title' => 'Nama panggilan', 'type' => 'Short answer', 'points' => 0, 'required' => false],
            ['id' => 10, 'title' => 'Soal IPA', 'description' => '', 'type' => 'Section', 'points' => 0, 'required' => false, 'goTo' => 'submit'],
            ['id' => 11, 'title' => 'Satuan gaya?', 'type' => 'Multiple choice', 'options' => ['Newton', 'Joule'], 'answer' => 'Newton', 'points' => 2, 'required' => true],
            ['id' => 20, 'title' => 'Soal IPS', 'description' => '', 'type' => 'Section', 'points' => 0, 'required' => false],
            ['id' => 21, 'title' => 'Ibu kota Jawa Barat?', 'type' => 'Short answer', 'answer' => 'Bandung', 'points' => 3, 'required' => true],
        ],
    ]);
});

test('the answers decide the way through the sections, like in Google Forms', function () {
    $items = $this->exam->questions;
    $settings = $this->exam->settings;

    expect(SectionFlow::path($items, $settings, [1 => 'IPA']))->toBe([0, 10])
        ->and(SectionFlow::path($items, $settings, [1 => 'IPS']))->toBe([0, 20])
        ->and(SectionFlow::path($items, $settings, [1 => 'Belum tahu']))->toBe([0])
        // Not answered yet: the section's own setting, then section 10 ends the form.
        ->and(SectionFlow::path($items, $settings, []))->toBe([0, 10])
        ->and(SectionFlow::questionIdsOnPath($items, $settings, [1 => 'IPS']))->toBe(['1', '2', '21'])
        ->and(SectionFlow::branches($items, $settings))->toBeTrue();

    // The first section leads on by its setting (kept in the settings: it has no section item).
    expect(SectionFlow::path($items, [...$settings, 'firstSectionGoTo' => 20], []))->toBe([0, 20]);

    // Back to a section already on the way ends the way there, instead of going round forever.
    $items[4]['goTo'] = 0;
    expect(SectionFlow::path($items, $settings, [1 => 'IPS']))->toBe([0, 20]);

    // A form whose sections all lead on does not branch: every question counts.
    $plain = array_map(fn (array $item): array => array_diff_key($item, array_flip(['goTo', 'branching', 'optionGoTo'])), $this->exam->questions);
    expect(SectionFlow::branches($plain, []))->toBeFalse()
        ->and(SectionFlow::questionIdsOnPath($plain, [], [1 => 'IPA']))->toBeNull()
        ->and(SectionFlow::path($plain, [], []))->toBe([0, 10, 20]);
});

test('a student is scored only on the sections on their way', function () {
    $student = User::factory()->create(['role' => 'siswa']);
    $token = $this->actingAs($student)->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('examSummary.branches', true))
        ->inertiaProps('session.token');
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token])->assertOk();

    // The exam page gets the flow with the questions.
    $this->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.questions.0.optionGoTo', [10, 20, 'submit'])
            ->where('quizForm.questions.2.goTo', 'submit'));

    // The page sends an answer from a section the student went back out of; it does not count.
    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $token,
        'answers' => [1 => 'IPS', 2 => 'Budi', 11 => 'Newton', 21 => 'Bandung'],
        'doubts' => ['11', '21'],
    ])->assertSuccessful();

    $response = QuizResponse::sole();
    expect($response->answers)->toBe([1 => 'IPS', 2 => 'Budi', 21 => 'Bandung'])
        ->and($response->question_ids)->toBe(['1', '2', '21'])
        ->and($response->doubtful_question_ids)->toBe(['21'])
        ->and($response->score)->toBe(3);

    // Full marks: the IPA question is not part of this student's maximum.
    $gradebook = $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.responses.gradebook');
    expect($gradebook[0])->toMatchArray(['score' => 3, 'maxScore' => 3, 'percentage' => 100]);
});

test('an exam handed in by the server when time runs out keeps the way as well', function () {
    Carbon::setTestNow('2026-10-16 08:00:00');
    $this->exam->update(['settings' => [...$this->exam->settings, 'timeLimit' => 30]]);

    QuizSession::create([
        'quiz_form_id' => $this->exam->id,
        'respondent_identifier' => 'anon_'.Str::random(16),
        'respondent_name' => 'Sari',
        'session_token' => Str::random(40),
        'started_at' => now()->subMinutes(32),
        'expires_at' => now()->subMinutes(2),
        'draft_answers' => [1 => 'IPA', 11 => 'Newton', 21 => 'Bandung'],
        'draft_saved_at' => now()->subMinutes(3),
    ]);

    expect(app(SubmitExpiredAttempts::class)->handle())->toBe(1);

    $response = QuizResponse::sole();
    expect($response->answers)->toBe([1 => 'IPA', 11 => 'Newton'])
        ->and($response->question_ids)->toBe(['1', '2', '11'])
        ->and($response->score)->toBe(2);

    Carbon::setTestNow();
});

test('the teacher saves the flow, and targets that point nowhere become "next"', function () {
    $questions = $this->exam->questions;
    $questions[0]['optionGoTo'] = [10, 99, 'submit', 'extra'];
    $questions[1]['goTo'] = 'submit';
    $questions[2]['goTo'] = 10;
    $questions[5]['branching'] = true;
    $questions[5]['optionGoTo'] = [10];

    $this->actingAs($this->teacher)->patch(route('forms.update', $this->exam), [
        'title' => $this->exam->title,
        'slug' => $this->exam->slug,
        'questions' => $questions,
        'settings' => [...$this->exam->settings, 'firstSectionGoTo' => 'submit'],
        'published' => true,
    ])->assertSessionHasNoErrors();

    $saved = $this->exam->fresh();
    expect($saved->questions[0]['branching'])->toBeTrue()
        // One target per option: the unknown section becomes "next", the extra one goes.
        ->and($saved->questions[0]['optionGoTo'])->toBe([10, 'next', 'submit'])
        // Only sections lead somewhere, never to themselves.
        ->and($saved->questions[1])->not->toHaveKey('goTo')
        ->and($saved->questions[2]['goTo'])->toBe('next')
        // Only choice questions send by their answer.
        ->and($saved->questions[5])->not->toHaveKeys(['branching', 'optionGoTo'])
        ->and($saved->settings['firstSectionGoTo'])->toBe('submit');
});

test('questions in skipped sections are not required, those on the way are', function () {
    $student = User::factory()->create(['role' => 'siswa']);
    $token = $this->actingAs($student)->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token])->assertOk();

    // The IPS question is on the way and required.
    $this->postJson(route('forms.responses.store', $this->exam->slug), ['session_token' => $token, 'answers' => [1 => 'IPS']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('answers.21')
        ->assertJsonMissingValidationErrors('answers.11');

    // The required IPA question was skipped: handed in without it.
    $this->postJson(route('forms.responses.store', $this->exam->slug), ['session_token' => $token, 'answers' => [1 => 'IPS', 21 => 'Bandung']])
        ->assertSuccessful();
});
