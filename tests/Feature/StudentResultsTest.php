<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru', 'quiz_preferences' => ['default_kkm' => 70]]);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 30],
        'questions' => [
            ['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4'], 'answer' => '4', 'points' => 2, 'explanation' => 'Dua ditambah dua sama dengan empat.'],
            ['id' => 2, 'title' => 'Ibu kota Jawa Barat?', 'type' => 'Short answer', 'answer' => 'Bandung', 'points' => 2],
            ['id' => 3, 'title' => 'Jelaskan fotosintesis', 'type' => 'Paragraph', 'points' => 4],
        ],
    ]);
    $this->student = User::factory()->create(['role' => 'siswa']);
    QuizResponse::query()->create([
        'quiz_form_id' => $this->exam->id, 'user_id' => $this->student->id, 'respondent_identifier' => 'user_'.$this->student->id,
        'answers' => [1 => '4', 2 => 'Surabaya', 3 => 'Tumbuhan memasak.'], 'score' => 2,
        'manual_scores' => [3 => 3], 'feedback' => [3 => 'Sebutkan juga peran klorofil.'],
    ]);
});

test('the pembahasan and the answer key never reach students while they take the exam', function () {
    $this->actingAs($this->student);
    $token = $this->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');

    // A second attempt (no limit): the questions handed out have neither key nor pembahasan.
    $questions = $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token])->assertOk()->json('questions');

    expect($questions[0])->not->toHaveKey('answer')->not->toHaveKey('explanation');
});

test('results are hidden until the teacher shows them', function () {
    $this->actingAs($this->student)->get(route('forms.results', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->component('QuizResult')
            ->where('isShown', false)
            ->where('attempts', []));
});

test('shown as a grade only, the student sees their grade but no key', function () {
    $this->exam->update(['settings' => [...$this->exam->settings, 'showResultsSummary' => true]]);

    $this->actingAs($this->student)->get(route('forms.results', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('isShown', true)
            ->where('isFull', false)
            ->where('kkm', 70)
            // 2 (right) + 0 (Surabaya) + 3 of 4 (essay) = 5 of 8.
            ->where('attempts.0.score', 5)
            ->where('attempts.0.max', 8)
            ->where('attempts.0.grade', 62.5)
            ->where('attempts.0.passed', false)
            ->where('attempts.0.questions', []));
});

test('shown in full, every question comes with the right answer, the pembahasan and the teacher\'s comment', function () {
    $this->exam->update(['settings' => [...$this->exam->settings, 'showResultsSummary' => true, 'resultsDetail' => 'full']]);

    $this->actingAs($this->student)->get(route('forms.results', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('isFull', true)
            ->where('attempts.0.questions.0.isCorrect', true)
            ->where('attempts.0.questions.0.explanation', 'Dua ditambah dua sama dengan empat.')
            ->where('attempts.0.questions.1.answer', 'Surabaya')
            ->where('attempts.0.questions.1.correctAnswer', 'Bandung')
            ->where('attempts.0.questions.1.isCorrect', false)
            ->where('attempts.0.questions.2.earned', 3)
            ->where('attempts.0.questions.2.waiting', false)
            ->where('attempts.0.questions.2.feedback', 'Sebutkan juga peran klorofil.'));
});

test('a student sees only their own results; the dashboard links to them', function () {
    $this->exam->update(['settings' => [...$this->exam->settings, 'showResultsSummary' => true]]);
    $other = User::factory()->create(['role' => 'siswa']);

    $this->actingAs($other)->get(route('forms.results', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('hasAttempts', false)->where('attempts', []));

    $card = collect($this->actingAs($this->student)->get(route('dashboard'))->inertiaProps('recentForms'))->firstWhere('slug', $this->exam->slug);
    expect($card['resultsUrl'])->toBe(route('forms.results', $this->exam->slug));
});

test('the editor saves the new settings and refuses odd values', function () {
    $save = fn (array $settings) => $this->actingAs($this->teacher)->patch(route('forms.update', $this->exam), [
        'title' => $this->exam->title, 'slug' => $this->exam->slug, 'questions' => $this->exam->questions,
        'settings' => [...$this->exam->settings, ...$settings],
    ]);

    $save(['showResultsSummary' => true, 'resultsDetail' => 'full', 'schedule' => ['opensAt' => '2026-10-20T01:00:00.000Z', 'closesAt' => '2026-10-20T03:00:00.000Z']])->assertSessionHasNoErrors();
    expect($this->exam->refresh()->settings)->toMatchArray(['resultsDetail' => 'full'])
        ->and($this->exam->questions[0]['explanation'])->toBe('Dua ditambah dua sama dengan empat.');

    $save(['resultsDetail' => 'semua'])->assertSessionHasErrors('settings.resultsDetail');
    $save(['schedule' => ['opensAt' => '2026-10-20T03:00:00Z', 'closesAt' => '2026-10-20T01:00:00Z']])->assertSessionHasErrors('settings.schedule.closesAt');
});
