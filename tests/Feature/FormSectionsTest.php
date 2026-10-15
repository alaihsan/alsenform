<?php

use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\QuestionBank;
use App\Support\ExamLayout;
use App\Support\QuizItems;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'shuffleQuestions' => true],
        'questions' => [
            ['id' => 1, 'title' => 'Soal A', 'type' => 'Multiple choice', 'options' => ['1', '2'], 'answer' => '1', 'points' => 1],
            ['id' => 2, 'title' => 'Soal B', 'type' => 'Multiple choice', 'options' => ['1', '2'], 'answer' => '1', 'points' => 1],
            ['id' => 3, 'title' => 'Bacaan', 'description' => 'Bacalah teks berikut untuk soal C dan D.', 'type' => 'Title and description', 'points' => 0, 'required' => false],
            ['id' => 4, 'title' => 'Soal C', 'type' => 'Multiple choice', 'options' => ['1', '2'], 'answer' => '1', 'points' => 1, 'required' => true],
            ['id' => 5, 'title' => 'Soal D', 'type' => 'Short answer', 'answer' => 'ya', 'points' => 1],
            ['id' => 6, 'title' => 'Bagian Esai', 'description' => 'Jawablah dengan kalimat sendiri.', 'type' => 'Section', 'points' => 0, 'required' => false],
            ['id' => 7, 'title' => 'Soal E', 'type' => 'Multiple choice', 'options' => ['1', '2'], 'answer' => '1', 'points' => 1],
        ],
    ]);
});

test('sections and title items are not questions: not counted, scored or graded by hand', function () {
    $student = User::factory()->create(['role' => 'siswa']);
    $this->actingAs($student)->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('examSummary.questionCount', 5)
            ->where('examSummary.totalPoints', 5)
            ->where('examSummary.requiredCount', 1)
            ->where('examSummary.sectionCount', 2));

    QuizResponse::query()->create([
        'quiz_form_id' => $this->exam->id, 'user_id' => $student->id, 'respondent_identifier' => 'user_'.$student->id,
        'answers' => [1 => '1', 2 => '1', 4 => '1', 5 => 'ya', 7 => '1'], 'score' => 5,
    ]);

    $responses = $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.responses');
    expect($responses['gradebook'][0])->toMatchArray(['score' => 5, 'maxScore' => 5, 'percentage' => 100, 'ungraded' => 0])
        ->and($responses['questions'])->toHaveCount(5)
        ->and($responses['ungradedTotal'])->toBe(0);

    // In the grading list, the short answer is question 4 (the title item has no number).
    $this->actingAs($this->teacher)->getJson(route('forms.grading.index', $this->exam))
        ->assertJsonCount(1, 'questions')
        ->assertJsonPath('questions.0.number', 4);

    // No column in the Excel answer sheet.
    $export = $this->actingAs($this->teacher)->get(route('forms.responses.export', $this->exam));
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    $answers = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
    $zip->close();
    expect($answers)->toContain('5. Soal E')->not->toContain('Bacaan')->not->toContain('Bagian Esai');
});

test('questions are shuffled within the stretch between a title or section, which stay in place', function () {
    foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'] as $seed) {
        $ids = array_map(fn (array $item) => $item['id'], app(ExamLayout::class)->arrange($this->exam, app(ExamLayout::class)->build($this->exam, $seed)));

        expect($ids[2])->toBe(3)
            ->and($ids[5])->toBe(6)
            ->and($ids[6])->toBe(7)
            ->and(array_slice($ids, 0, 2))->toEqualCanonicalizing([1, 2])
            ->and(array_slice($ids, 3, 2))->toEqualCanonicalizing([4, 5]);
    }
});

test('a draw picks questions only; the items stay, and are not assigned as questions', function () {
    $this->exam->update(['settings' => [...$this->exam->settings, 'questionDraw' => ['enabled' => true, 'mode' => 'count', 'count' => 3]]]);

    $layout = app(ExamLayout::class)->build($this->exam, 'seed');
    $arranged = app(ExamLayout::class)->arrange($this->exam, $layout);

    expect(QuizItems::questions($arranged))->toHaveCount(3)
        ->and(collect($arranged)->pluck('id')->intersect([3, 6])->values()->all())->toBe([3, 6])
        ->and(app(ExamLayout::class)->assignedIds($this->exam, $layout))->toHaveCount(3)
        ->and(app(ExamLayout::class)->assignedIds($this->exam, $layout))->not->toContain('3');
});

test('they are not kept in the question bank, and may go without a title', function () {
    app(QuestionBank::class)->sync($this->exam);
    expect(QuestionBankItem::query()->where('quiz_form_id', $this->exam->id)->pluck('source_question_id')->sort()->values()->all())
        ->toBe(['1', '2', '4', '5', '7']);

    $questions = $this->exam->questions;
    $questions[2]['title'] = '';
    $this->actingAs($this->teacher)->patch(route('forms.update', $this->exam), [
        'title' => $this->exam->title, 'slug' => $this->exam->slug, 'questions' => $questions, 'settings' => $this->exam->settings,
    ])->assertSessionHasNoErrors();

    $questions[0]['title'] = '';
    $this->actingAs($this->teacher)->patch(route('forms.update', $this->exam), [
        'title' => $this->exam->title, 'slug' => $this->exam->slug, 'questions' => $questions, 'settings' => $this->exam->settings,
    ])->assertSessionHasErrors('questions.0.title');
});
