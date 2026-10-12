<?php

use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\User;
use App\Services\QuestionBank;

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function bankQuestion(int $id, string $title, array $attributes = []): array
{
    return array_merge([
        'id' => $id,
        'title' => $title,
        'description' => '',
        'type' => 'Multiple choice',
        'options' => ['Jakarta', 'Bandung', 'Surabaya'],
        'rows' => [],
        'columns' => [],
        'answer' => 0,
        'required' => true,
        'media' => [],
        'points' => 2,
    ], $attributes);
}

/**
 * @param  list<array<string, mixed>>  $questions
 */
function saveExam(QuizForm $exam, array $questions): void
{
    test()->actingAs($exam->user)->patch(route('forms.update', $exam), [
        'title' => $exam->title,
        'slug' => $exam->slug,
        'questions' => $questions,
        'settings' => $exam->settings,
    ])->assertRedirect();

    $exam->refresh();
}

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create(['title' => 'PTS IPS Kelas 8']);
});

test('every question a teacher writes is kept in their question bank', function () {
    saveExam($this->exam, [
        bankQuestion(1, 'Ibu kota <b>Indonesia</b> adalah?'),
        bankQuestion(2, 'Untitled Question'),
        bankQuestion(3, 'Short answer question', ['type' => 'Short answer', 'options' => []]),
        bankQuestion(4, 'Jelaskan proses fotosintesis!', ['type' => 'Paragraph', 'options' => [], 'answer' => '']),
    ]);

    $items = QuestionBankItem::query()->orderBy('source_question_id')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0])
        ->user_id->toBe($this->teacher->id)
        ->quiz_form_id->toBe($this->exam->id)
        ->type->toBe('Multiple choice')
        ->search_text->toContain('ibu kota indonesia adalah?')->toContain('surabaya')
        ->and($items[0]->question)->not->toHaveKey('id')
        ->and($items[0]->question['answer'])->toBe(0)
        ->and($items[1]->type)->toBe('Paragraph');
});

test('an edited question is updated in the bank and a removed question stays there', function () {
    saveExam($this->exam, [bankQuestion(1, 'Ibu kota Indonesia?'), bankQuestion(2, 'Ibu kota Jawa Barat?')]);
    $firstItem = QuestionBankItem::query()->where('source_question_id', '1')->sole();

    saveExam($this->exam, [bankQuestion(1, 'Ibu kota negara Indonesia adalah?')]);

    expect(QuestionBankItem::query()->count())->toBe(2)
        ->and($firstItem->refresh()->question['title'])->toBe('Ibu kota negara Indonesia adalah?')
        ->and(QuestionBankItem::query()->where('source_question_id', '2')->sole()->question['title'])->toBe('Ibu kota Jawa Barat?');

    $this->exam->delete();
    $this->exam->forceDelete();

    expect(QuestionBankItem::query()->count())->toBe(2)
        ->and(QuestionBankItem::query()->whereNull('quiz_form_id')->count())->toBe(2);
});

test('the bank tab lists the teacher own questions once, newest first', function () {
    saveExam($this->exam, [bankQuestion(1, 'Ibu kota Indonesia?'), bankQuestion(2, 'Lambang negara Indonesia?')]);
    // A copied exam holds the same questions: they are listed once.
    $copy = QuizForm::factory()->for($this->teacher)->create(['title' => 'Salinan']);
    saveExam($copy, [bankQuestion(7, 'Ibu kota Indonesia?')]);
    $otherTeacher = User::factory()->create(['role' => 'guru']);
    QuestionBankItem::factory()->for($otherTeacher)->create();

    $response = $this->actingAs($this->teacher)
        ->getJson(route('question-bank.index', ['exam' => $copy->id]))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('hasMore', false)
        ->assertJsonPath('types', ['Multiple choice'])
        ->assertJsonCount(2, 'forms');

    $items = collect($response->json('items'));

    expect($items->pluck('title')->sort()->values()->all())->toBe(['Ibu kota Indonesia?', 'Lambang negara Indonesia?'])
        ->and($items->firstWhere('title', 'Ibu kota Indonesia?'))
        ->toMatchArray([
            'type' => 'Multiple choice',
            'options' => ['Jakarta', 'Bandung', 'Surabaya'],
            'points' => 2,
            'hasAnswerKey' => true,
            'inThisExam' => true,
        ])
        ->and($items->firstWhere('title', 'Lambang negara Indonesia?')['inThisExam'])->toBeFalse()
        ->and($items->firstWhere('title', 'Lambang negara Indonesia?')['source']['title'])->toBe('PTS IPS Kelas 8');
});

test('the bank is searched by words in the question and its options, and filtered by type and exam', function () {
    saveExam($this->exam, [
        bankQuestion(1, 'Ibu kota Indonesia?', ['options' => ['Jakarta', 'Bandung']]),
        bankQuestion(2, 'Kota pahlawan adalah?', ['options' => ['Medan', 'SURABAYA']]),
        bankQuestion(3, 'Ceritakan pengalamanmu di kota!', ['type' => 'Paragraph', 'options' => []]),
        bankQuestion(4, 'Diskon 100% berarti?', ['options' => ['Gratis', 'Setengah harga']]),
    ]);
    $otherExam = QuizForm::factory()->for($this->teacher)->create();
    saveExam($otherExam, [bankQuestion(1, 'Kota terbesar di Kalimantan?', ['options' => ['Samarinda', 'Balikpapan']])]);

    $titles = fn (array $query) => collect($this->actingAs($this->teacher)->getJson(route('question-bank.index', $query))->json('items'))->pluck('title')->sort()->values()->all();

    expect($titles(['search' => 'IBU kota']))->toBe(['Ibu kota Indonesia?'])
        ->and($titles(['search' => 'surabaya']))->toBe(['Kota pahlawan adalah?'])
        ->and($titles(['search' => 'kota']))->toBe(['Ceritakan pengalamanmu di kota!', 'Ibu kota Indonesia?', 'Kota pahlawan adalah?', 'Kota terbesar di Kalimantan?'])
        ->and($titles(['search' => 'kota', 'type' => 'Paragraph']))->toBe(['Ceritakan pengalamanmu di kota!'])
        ->and($titles(['search' => 'kota', 'form' => $otherExam->id]))->toBe(['Kota terbesar di Kalimantan?'])
        ->and($titles(['search' => '100%']))->toBe(['Diskon 100% berarti?'])
        ->and($titles(['search' => '_']))->toBe([]);
});

test('the bank is paged', function () {
    saveExam($this->exam, collect(range(1, QuestionBank::PER_PAGE + 5))->map(fn (int $id) => bankQuestion($id, "Soal nomor {$id}"))->all());

    $this->actingAs($this->teacher)->getJson(route('question-bank.index'))
        ->assertJsonCount(QuestionBank::PER_PAGE, 'items')
        ->assertJsonPath('total', QuestionBank::PER_PAGE + 5)
        ->assertJsonPath('hasMore', true);

    $this->actingAs($this->teacher)->getJson(route('question-bank.index', ['page' => 2]))
        ->assertJsonCount(5, 'items')
        ->assertJsonPath('hasMore', false);
});

test('opening the bank brings in the questions of exams written before the bank existed', function () {
    $this->exam->forceDelete();
    QuizForm::factory()->for($this->teacher)->create(['questions' => [bankQuestion(1, 'Soal lama dari tahun lalu')]]);
    $trashed = QuizForm::factory()->for($this->teacher)->create(['questions' => [bankQuestion(1, 'Soal di ujian yang dibuang')]]);
    $trashed->delete();

    $this->actingAs($this->teacher)->getJson(route('question-bank.index'))->assertJsonPath('total', 0);

    $this->actingAs($this->teacher)
        ->getJson(route('question-bank.index', ['sync' => 1]))
        ->assertJsonPath('total', 2)
        ->assertJsonFragment(['title' => 'Soal di ujian yang dibuang', 'source' => ['title' => $trashed->title, 'trashed' => true]]);
});

test('chosen bank questions come back complete, in the chosen order, for the teacher only', function () {
    saveExam($this->exam, [
        bankQuestion(1, 'Ibu kota Indonesia?', ['media' => [['type' => 'image', 'url' => '/storage/media/peta.png']]]),
        bankQuestion(2, 'Lambang negara?', ['answer' => 2, 'points' => 5]),
    ]);
    $first = QuestionBankItem::query()->where('source_question_id', '1')->sole();
    $second = QuestionBankItem::query()->where('source_question_id', '2')->sole();
    $foreign = QuestionBankItem::factory()->for(User::factory()->create(['role' => 'guru']))->create();

    $questions = $this->actingAs($this->teacher)
        ->postJson(route('question-bank.questions'), ['ids' => [$second->id, $foreign->id, $first->id]])
        ->assertOk()
        ->json('questions');

    expect($questions)->toHaveCount(2)
        ->and($questions[0])->toMatchArray(['title' => 'Lambang negara?', 'answer' => 2, 'points' => 5])
        ->and($questions[1]['media'])->toEqual([['type' => 'image', 'url' => '/storage/media/peta.png']]);

    $this->actingAs($this->teacher)->postJson(route('question-bank.questions'), ['ids' => []])->assertUnprocessable();
});

test('students and guests cannot open a question bank', function () {
    saveExam($this->exam, [bankQuestion(1, 'Ibu kota Indonesia?')]);
    $item = QuestionBankItem::query()->sole();
    $student = User::factory()->create(['role' => 'siswa']);
    auth()->logout();

    $this->getJson(route('question-bank.index'))->assertUnauthorized();
    $this->postJson(route('question-bank.questions'), ['ids' => [$item->id]])->assertUnauthorized();

    $this->actingAs($student)->getJson(route('question-bank.index'))->assertForbidden();
    $this->actingAs($student)->postJson(route('question-bank.questions'), ['ids' => [$item->id]])->assertForbidden();
});

test('a teacher editing someone else\'s exam does not see its owner\'s bank questions in their own bank', function () {
    saveExam($this->exam, [bankQuestion(1, 'Soal rahasia milik pemilik')]);
    $collaborator = User::factory()->create(['role' => 'guru']);
    $this->exam->collaborators()->attach($collaborator->id, ['role' => 'editor']);

    $this->actingAs($collaborator)
        ->getJson(route('question-bank.index', ['sync' => 1, 'exam' => $this->exam->id]))
        ->assertJsonPath('total', 0);

    expect(QuestionBankItem::query()->sole()->user_id)->toBe($this->teacher->id);
});

test('bringing the bank up to date again leaves unchanged questions alone', function () {
    saveExam($this->exam, [bankQuestion(1, 'Ibu kota Indonesia?', ['media' => [['type' => 'image', 'url' => '/storage/media/peta.png', 'width' => '50%']]])]);
    $savedAt = QuestionBankItem::query()->sole()->updated_at;

    // Read back from the database (MySQL reorders the keys of JSON objects): still the same question.
    $this->travel(5)->minutes();
    app(QuestionBank::class)->syncAllFor($this->teacher);

    expect(QuestionBankItem::query()->sole()->updated_at->equalTo($savedAt))->toBeTrue();

    $this->actingAs($this->teacher)
        ->getJson(route('question-bank.index', ['exam' => $this->exam->id]))
        ->assertJsonPath('items.0.inThisExam', true);
});
