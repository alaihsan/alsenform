<?php

use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\ItemAnalysis;
use App\Services\QuestionBank;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Ten students: the first five strong, the last five weak.
 * - Soal 1: everyone right (too easy, no discrimination).
 * - Soal 2: only the strong right; the weak all pick A (B, D and E nobody picks).
 * - Soal 3: the strong pick B, the weak pick the key (a wrong key).
 * - Soal 4: only the strong right; the weak spread over the other choices.
 * - Soal 5: an essay.
 */
beforeEach(function () {
    $choice = fn (int $id, int $key) => ['id' => $id, 'title' => "Soal {$id}", 'type' => 'Multiple choice', 'options' => ["A{$id}", "B{$id}", "C{$id}", "D{$id}", "E{$id}"], 'answer' => $key, 'points' => 1];
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'questions' => [$choice(1, 0), $choice(2, 2), $choice(3, 0), $choice(4, 3), ['id' => 5, 'title' => 'Jelaskan', 'type' => 'Paragraph', 'answer' => '', 'points' => 5]],
    ]);

    $weakChoices4 = ['A4', 'A4', 'B4', 'C4', 'E4'];
    foreach (range(1, 10) as $student) {
        $strong = $student <= 5;
        QuizResponse::query()->create([
            'quiz_form_id' => $this->exam->id,
            'respondent_identifier' => "anon_{$student}",
            'answers' => [
                1 => 'A1',
                2 => $strong ? 'C2' : 'A2',
                3 => $strong ? 'B3' : 'A3',
                4 => $strong ? 'D4' : $weakChoices4[$student - 6],
                5 => 'Uraian siswa',
            ],
            'score' => 0,
        ]);
    }
});

test('each question gets its difficulty, discrimination, distractors and a decision', function () {
    $analysis = app(ItemAnalysis::class)->analyze($this->exam);
    $byNumber = collect($analysis['questions'])->keyBy('number');

    expect($analysis)->toMatchArray(['respondents' => 10, 'reliable' => true])
        ->and($analysis['summary'])->toMatchArray(['mudah' => 1, 'sedang' => 3, 'sukar' => 0, 'dipakai' => 1, 'revisi' => 1, 'ganti' => 2]);

    expect($byNumber[1])->toMatchArray(['p' => 1.0, 'd' => 0.0, 'weakDistractors' => ['B', 'C', 'D', 'E']])
        ->and($byNumber[1]['difficulty']['label'])->toBe('Mudah')
        ->and($byNumber[1]['discrimination']['label'])->toBe('Jelek')
        ->and($byNumber[1]['decision']['label'])->toBe('Ganti');

    expect($byNumber[2])->toMatchArray(['correct' => 5, 'takers' => 10, 'p' => 0.5, 'd' => 1.0, 'weakDistractors' => ['B', 'D', 'E']])
        ->and($byNumber[2]['discrimination']['label'])->toBe('Baik sekali')
        ->and($byNumber[2]['decision']['label'])->toBe('Revisi')
        ->and($byNumber[2]['options'][0])->toMatchArray(['letter' => 'A', 'picked' => 5, 'share' => 0.5, 'isKey' => false, 'works' => true])
        ->and($byNumber[2]['options'][2])->toMatchArray(['letter' => 'C', 'isKey' => true]);

    expect($byNumber[3])->toMatchArray(['p' => 0.5, 'd' => -1.0])
        ->and($byNumber[3]['discrimination']['label'])->toBe('Negatif')
        ->and($byNumber[3]['decision']['label'])->toBe('Ganti')
        ->and($byNumber[3]['note'])->toContain('periksa kunci');

    expect($byNumber[4])->toMatchArray(['p' => 0.5, 'd' => 1.0, 'weakDistractors' => []])
        ->and($byNumber[4]['decision']['label'])->toBe('Dipakai');

    expect($byNumber[5])->toMatchArray(['analyzed' => false, 'note' => 'Esai, dinilai guru', 'decision' => null]);
});

test('the analysis is shown in the results and in the excel workbook', function () {
    $this->actingAs($this->teacher)
        ->get(route('forms.edit', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.analysis.respondents', 10)
            ->where('quizForm.responses.analysis.questions.3.decision.label', 'Dipakai'));

    $export = $this->actingAs($this->teacher)->get(route('forms.responses.export', $this->exam));
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet3.xml');
    $workbook = (string) $zip->getFromName('xl/workbook.xml');
    $zip->close();

    expect($workbook)->toContain('Analisis Butir Soal')
        ->and($sheet)->toContain('Baik sekali')->toContain('Negatif')->toContain('periksa kunci');
});

test('few students are marked as not yet reliable', function () {
    QuizResponse::query()->where('quiz_form_id', $this->exam->id)->limit(5)->delete();

    expect(app(ItemAnalysis::class)->analyze($this->exam))->toMatchArray(['respondents' => 5, 'reliable' => false]);
});

test('the difficulty found becomes the difficulty label in the question bank', function () {
    app(QuestionBank::class)->sync($this->exam);

    $this->actingAs($this->teacher)
        ->postJson(route('forms.analysis.apply-difficulty', $this->exam))
        ->assertOk()
        ->assertJsonPath('labelled', 4)
        ->assertJsonPath('labels.1.difficulty', 'mudah')
        ->assertJsonPath('labels.2.difficulty', 'sedang');

    expect(QuestionBankItem::query()->where('source_question_id', '5')->sole()->difficulty)->toBeNull();

    $this->actingAs(User::factory()->create(['role' => 'guru']))
        ->postJson(route('forms.analysis.apply-difficulty', $this->exam))
        ->assertForbidden();
});

test('with questions drawn per student, a question is analysed over the students who got it', function () {
    QuizResponse::query()->where('quiz_form_id', $this->exam->id)->get()->each(
        fn (QuizResponse $response, int $index) => $response->update(['question_ids' => $index < 4 ? ['1', '2'] : ['1', '2', '3', '4', '5']]),
    );

    $byNumber = collect(app(ItemAnalysis::class)->analyze($this->exam)['questions'])->keyBy('number');

    expect($byNumber[1]['takers'])->toBe(10)
        ->and($byNumber[3]['takers'])->toBe(6);
});

/**
 * Ten students over five questions (key A everywhere); soal 1 is the one looked at.
 * Two score 100, four tie at 60 on the edge of the top group (two of them right on soal 1),
 * four score 20. The top group (3 places) is the two at 100 plus a quarter of each of the four.
 *
 * @param  list<string>  $order
 */
function tiedExam(User $teacher, array $order): QuizForm
{
    $questions = array_map(fn (int $id) => ['id' => $id, 'title' => "Soal {$id}", 'type' => 'Multiple choice', 'options' => ["A{$id}", "B{$id}", "C{$id}", "D{$id}", "E{$id}"], 'answer' => 0, 'points' => 1], range(1, 5));
    $exam = QuizForm::factory()->for($teacher)->create(['published_at' => now(), 'questions' => $questions]);

    $answers = fn (string $first, int $rightFillers) => [1 => "{$first}1", ...array_combine(range(2, 5), array_map(fn (int $id) => ($id - 1 <= $rightFillers ? 'A' : 'B').$id, range(2, 5)))];
    $students = [
        's1' => $answers('A', 4), 's2' => $answers('A', 4),
        's3' => $answers('A', 2), 's4' => $answers('A', 2), 's5' => $answers('B', 3), 's6' => $answers('B', 3),
        's7' => $answers('B', 1), 's8' => $answers('B', 1), 's9' => $answers('B', 1), 's10' => $answers('B', 1),
    ];

    foreach ($order as $student) {
        QuizResponse::query()->create(['quiz_form_id' => $exam->id, 'respondent_identifier' => $student, 'answers' => $students[$student], 'score' => 0]);
    }

    return $exam;
}

test('students tied at the edge of a group share it, so the result never depends on the order of the answers', function () {
    $inOrder = ['s1', 's2', 's3', 's4', 's5', 's6', 's7', 's8', 's9', 's10'];
    $analyses = collect([$inOrder, array_reverse($inOrder), ['s5', 's6', 's1', 's7', 's3', 's8', 's2', 's9', 's4', 's10']])
        ->map(fn (array $order) => app(ItemAnalysis::class)->analyze(tiedExam($this->teacher, $order))['questions'][0]);

    // Top group: 2 right + a quarter of 2 right = 2,5 of 3; bottom group: none right.
    expect($analyses->pluck('d')->unique()->values()->all())->toBe([0.83])
        ->and($analyses[0])->toMatchArray(['p' => 0.4, 'correct' => 4, 'weakDistractors' => ['C', 'D', 'E']])
        ->and($analyses[0]['discrimination']['label'])->toBe('Baik sekali')
        ->and($analyses[0]['decision']['label'])->toBe('Revisi')
        ->and($analyses[0]['note'])->toContain('Pengecoh C, D, E hampir tidak dipilih');
});

test('a question taken by fewer than ten students gets its difficulty only, not a decision', function () {
    QuizResponse::query()->where('quiz_form_id', $this->exam->id)->limit(1)->delete();

    $analysis = app(ItemAnalysis::class)->analyze($this->exam);
    $first = $analysis['questions'][1];

    expect($first)->toMatchArray(['takers' => 9, 'd' => null, 'discrimination' => null, 'decision' => null, 'weakDistractors' => []])
        ->and($first['p'])->not->toBeNull()
        ->and($first['note'])->toContain('minimal 10 siswa')
        ->and(collect($first['options'])->pluck('works')->unique()->all())->toBe([null])
        ->and($analysis['summary'])->toMatchArray(['dipakai' => 0, 'revisi' => 0, 'ganti' => 0]);
});

test('each decision says why', function () {
    $byNumber = collect(app(ItemAnalysis::class)->analyze($this->exam)['questions'])->keyBy('number');

    expect($byNumber[1]['note'])->toContain('Terlalu mudah')
        ->and($byNumber[2]['note'])->toContain('Pengecoh B, D, E hampir tidak dipilih')
        ->and($byNumber[4]['note'])->toBeNull();
});

test('the results page and the excel workbook show the same analysis', function () {
    $analysis = null;
    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam->slug))
        ->assertInertia(function (Assert $page) use (&$analysis) {
            $analysis = $page->toArray()['props']['quizForm']['responses']['analysis'];
        });

    expect($analysis)->toEqual(app(ItemAnalysis::class)->analyze($this->exam->refresh()));
});
