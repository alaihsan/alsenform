<?php

use App\Actions\SubmitExpiredAttempts;
use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\QuestionBank;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $settings
 */
function layoutExam(array $settings = [], int $questionCount = 6): QuizForm
{
    $questions = [];
    foreach (range(1, $questionCount) as $id) {
        $questions[] = [
            'id' => $id,
            'title' => "Soal {$id}",
            'type' => 'Multiple choice',
            'options' => ["A{$id}", "B{$id}", "C{$id}", "D{$id}", "E{$id}"],
            'answer' => 2,
            'required' => true,
            'points' => 1,
        ];
    }

    return QuizForm::factory()->create([
        'published_at' => now(),
        'questions' => $questions,
        'settings' => array_merge(['collectEmail' => false, 'showProgress' => false, 'shuffleQuestions' => false], $settings),
    ]);
}

/**
 * Open the exam as a new student (a new browser) and start it.
 *
 * @return array{token: string, questions: list<array<string, mixed>>}
 */
function startAsNewStudent(QuizForm $quizForm, ?User $student = null): array
{
    $student ??= User::factory()->create(['role' => 'siswa']);
    $token = test()->actingAs($student)->get(route('forms.public', $quizForm->slug))->inertiaProps('session.token');
    $questions = test()->actingAs($student)
        ->postJson(route('forms.responses.start', $quizForm->slug), ['session_token' => $token])
        ->assertOk()
        ->json('questions');

    return ['token' => $token, 'questions' => $questions, 'student' => $student];
}

test('each student gets the choices in their own order, the same after a reload', function () {
    $quizForm = layoutExam(['shuffleOptions' => true]);

    $orders = collect(range(1, 6))->map(function () use ($quizForm) {
        $attempt = startAsNewStudent($quizForm);

        // Reloading the page shows the same order.
        test()->actingAs($attempt['student'])
            ->get(route('forms.public', $quizForm->slug))
            ->assertInertia(fn (Assert $page) => $page->where('quizForm.questions', $attempt['questions']));

        return $attempt['questions'][0]['options'];
    });

    expect($orders->every(fn (array $options) => collect($options)->sort()->values()->all() === ['A1', 'B1', 'C1', 'D1', 'E1']))->toBeTrue()
        ->and($orders->unique(fn (array $options) => implode(',', $options))->count())->toBeGreaterThan(1);
});

test('choices are graded by their text whatever order the student saw them in', function () {
    $quizForm = layoutExam(['shuffleOptions' => true], questionCount: 2);
    $attempt = startAsNewStudent($quizForm);

    $this->actingAs($attempt['student'])->postJson(route('forms.responses.store', $quizForm->slug), [
        'session_token' => $attempt['token'],
        'answers' => [1 => 'C1', 2 => 'A2'],
    ])->assertOk();

    expect(QuizResponse::query()->sole()->score)->toBe(1);
});

test('choices that point at other choices keep their order', function () {
    $quizForm = layoutExam(['shuffleOptions' => true], questionCount: 1);
    $quizForm->update(['questions' => [[
        'id' => 1, 'title' => 'Pilih', 'type' => 'Multiple choice', 'answer' => 3, 'points' => 1,
        'options' => ['Hujan', 'Angin', 'Petir', 'Semua jawaban benar'],
    ]]]);

    foreach (range(1, 4) as $student) {
        expect(startAsNewStudent($quizForm)['questions'][0]['options'])->toBe(['Hujan', 'Angin', 'Petir', 'Semua jawaban benar']);
    }
});

test('the question order is shuffled on the server and kept for the attempt', function () {
    $quizForm = layoutExam(['shuffleQuestions' => true], questionCount: 8);

    $orders = collect(range(1, 5))->map(fn () => array_column(startAsNewStudent($quizForm)['questions'], 'id'));

    expect($orders->every(fn (array $ids) => collect($ids)->sort()->values()->all() === range(1, 8)))->toBeTrue()
        ->and($orders->unique(fn (array $ids) => implode(',', $ids))->count())->toBeGreaterThan(1);
});

test('settings changed before the student starts apply, after the start the exam stays as it was', function () {
    $quizForm = layoutExam(questionCount: 3);
    $student = User::factory()->create(['role' => 'siswa']);
    $token = $this->actingAs($student)->get(route('forms.public', $quizForm->slug))->inertiaProps('session.token');

    // The teacher switches choice shuffling on while the student reads the rules.
    $quizForm->update(['settings' => array_merge($quizForm->settings, ['shuffleOptions' => true])]);

    $started = collect(range(1, 3))->map(fn () => $this->actingAs($student)
        ->postJson(route('forms.responses.start', $quizForm->slug), ['session_token' => $token])
        ->json('questions'));
    expect($started->unique(fn ($questions) => json_encode($questions))->count())->toBe(1);

    $layout = QuizSession::query()->firstWhere('session_token', $token)->layout;
    expect($layout['options'])->toHaveCount(3);

    // Switched off again during the exam: the student keeps the order they started with.
    $quizForm->update(['settings' => array_merge($quizForm->settings, ['shuffleOptions' => false])]);
    $this->actingAs($student)
        ->get(route('forms.public', $quizForm->slug))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.questions', $started->first()));
});

test('a question added during the exam reaches the students already working', function () {
    $quizForm = layoutExam(questionCount: 2);
    $attempt = startAsNewStudent($quizForm);

    $quizForm->update(['questions' => [...$quizForm->questions, ['id' => 3, 'title' => 'Soal susulan', 'type' => 'Short answer', 'answer' => 'x', 'points' => 1]]]);

    $this->actingAs($attempt['student'])
        ->get(route('forms.public', $quizForm->slug))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.questions.2.title', 'Soal susulan'));
});

test('a new exam takes the defaults the teacher saved in their profile', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'quiz_preferences' => [
        'default_shuffle_questions' => true,
        'default_shuffle_options' => true,
        'default_anti_cheat_blur' => true,
        'default_duration' => 45,
    ]]);

    $this->actingAs($teacher)->get(route('forms.create'))->assertRedirect();

    expect(QuizForm::query()->latest('id')->first()->settings)->toMatchArray([
        'shuffleQuestions' => true,
        'shuffleOptions' => true,
        'lockOnBlur' => true,
        'timeLimit' => 45,
    ]);

    $plain = User::factory()->create(['role' => 'guru']);
    $this->actingAs($plain)->get(route('forms.create'))->assertRedirect();

    expect(QuizForm::query()->latest('id')->first()->settings)
        ->toMatchArray(['shuffleQuestions' => false, 'shuffleOptions' => false])
        ->not->toHaveKey('lockOnBlur')
        ->not->toHaveKey('timeLimit');
});

test('each student gets the set number of questions drawn at random, kept for the attempt', function () {
    $quizForm = layoutExam(['questionDraw' => ['enabled' => true, 'mode' => 'count', 'count' => 3]], questionCount: 8);

    $sets = collect(range(1, 6))->map(function () use ($quizForm) {
        $student = User::factory()->create(['role' => 'siswa']);
        test()->actingAs($student)
            ->get(route('forms.public', $quizForm->slug))
            ->assertInertia(fn (Assert $page) => $page->where('examSummary.questionCount', 3)->where('examSummary.totalPoints', 3));

        $attempt = startAsNewStudent($quizForm, $student);
        test()->actingAs($student)
            ->get(route('forms.public', $quizForm->slug))
            ->assertInertia(fn (Assert $page) => $page->where('quizForm.questions', $attempt['questions']));

        return array_column($attempt['questions'], 'id');
    });

    expect($sets->every(fn (array $ids) => count($ids) === 3 && count(array_unique($ids)) === 3))->toBeTrue()
        ->and($sets->unique(fn (array $ids) => implode(',', $ids))->count())->toBeGreaterThan(1);
});

test('a student is graded on the questions they got, and cannot answer the others', function () {
    $quizForm = layoutExam(['questionDraw' => ['enabled' => true, 'mode' => 'count', 'count' => 2]], questionCount: 6);
    $attempt = startAsNewStudent($quizForm);
    $given = array_column($attempt['questions'], 'id');
    $notGiven = array_values(array_diff(range(1, 6), $given));

    // Every answer correct, also to questions the student did not get (copied from a classmate).
    $answers = collect(range(1, 6))->mapWithKeys(fn (int $id) => [$id => "C{$id}"])->all();

    $this->actingAs($attempt['student'])->postJson(route('forms.responses.draft', $quizForm->slug), [
        'session_token' => $attempt['token'],
        'answers' => $answers,
    ])->assertOk();
    expect(array_keys(QuizSession::query()->firstWhere('session_token', $attempt['token'])->draft_answers))->toEqualCanonicalizing($given);

    // Only the questions given are required.
    $this->actingAs($attempt['student'])->postJson(route('forms.responses.store', $quizForm->slug), [
        'session_token' => $attempt['token'],
        'answers' => [$notGiven[0] => "C{$notGiven[0]}"],
    ])->assertUnprocessable()->assertJsonValidationErrors(["answers.{$given[0]}", "answers.{$given[1]}"])->assertJsonMissingValidationErrors(["answers.{$notGiven[1]}"]);

    $this->actingAs($attempt['student'])->postJson(route('forms.responses.store', $quizForm->slug), [
        'session_token' => $attempt['token'],
        'answers' => $answers,
    ])->assertOk();

    $response = QuizResponse::query()->sole();
    expect($response->score)->toBe(2)
        ->and(array_map('intval', array_keys($response->answers)))->toEqualCanonicalizing($given)
        ->and(array_map('intval', $response->question_ids))->toEqualCanonicalizing($given);

    // The gradebook counts 2 out of the 2 points this student could earn: 100.
    $this->actingAs($quizForm->user)
        ->get(route('forms.edit', $quizForm->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.gradebook.0.score', 2)
            ->where('quizForm.responses.gradebook.0.maxScore', 2)
            ->where('quizForm.responses.gradebook.0.percentage', 100));

    $export = $this->actingAs($quizForm->user)->get(route('forms.responses.export', $quizForm));
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    $answerSheet = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
    $zip->close();
    expect(substr_count($answerSheet, 'soal tidak didapat'))->toBe(4);
});

test('by difficulty, each student gets the set number of each, and unlabelled questions always', function () {
    $quizForm = layoutExam(['questionDraw' => ['enabled' => true, 'mode' => 'difficulty', 'perDifficulty' => ['mudah' => 2, 'sedang' => 1, 'sulit' => 1]]], questionCount: 9);
    app(QuestionBank::class)->sync($quizForm);
    $labels = [1 => 'mudah', 2 => 'mudah', 3 => 'mudah', 4 => 'sedang', 5 => 'sedang', 6 => 'sulit', 7 => 'sulit'];
    foreach ($labels as $questionId => $difficulty) {
        QuestionBankItem::query()->where('quiz_form_id', $quizForm->id)->where('source_question_id', (string) $questionId)->update(['difficulty' => $difficulty]);
    }

    foreach (range(1, 5) as $student) {
        $ids = array_column(startAsNewStudent($quizForm)['questions'], 'id');
        $count = fn (string $difficulty) => count(array_filter($ids, fn (int $id) => ($labels[$id] ?? null) === $difficulty));

        expect($ids)->toHaveCount(6)->toContain(8, 9)
            ->and($count('mudah'))->toBe(2)
            ->and($count('sedang'))->toBe(1)
            ->and($count('sulit'))->toBe(1);
    }
});

test('the server hands in only the questions of the attempt when the time runs out', function () {
    $quizForm = layoutExam(['timeLimit' => 10, 'questionDraw' => ['enabled' => true, 'mode' => 'count', 'count' => 2]], questionCount: 5);
    $attempt = startAsNewStudent($quizForm);
    $session = QuizSession::query()->firstWhere('session_token', $attempt['token']);
    $session->update(['draft_answers' => collect(range(1, 5))->mapWithKeys(fn (int $id) => [$id => "C{$id}"])->all(), 'expires_at' => now()->subMinutes(5)]);

    app(SubmitExpiredAttempts::class)->handle();

    $response = QuizResponse::query()->sole();
    expect($response->score)->toBe(2)
        ->and(array_map('intval', $response->question_ids))->toEqualCanonicalizing(array_column($attempt['questions'], 'id'));
});
