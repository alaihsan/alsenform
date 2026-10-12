<?php

use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\User;
use App\Services\QuestionBank;
use Inertia\Testing\AssertableInertia;

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

test('labels are given to a question and every copy of it, and only the labels sent change', function () {
    saveExam($this->exam, [bankQuestion(1, 'Ibu kota Indonesia?'), bankQuestion(2, 'Lambang negara?')]);
    $copy = QuizForm::factory()->for($this->teacher)->create();
    saveExam($copy, [bankQuestion(5, 'Ibu kota Indonesia?')]);
    $item = QuestionBankItem::query()->where('quiz_form_id', $this->exam->id)->where('source_question_id', '1')->sole();

    $this->actingAs($this->teacher)
        ->patchJson(route('question-bank.labels'), ['ids' => [$item->id], 'subject' => ' IPS ', 'grade' => '8', 'topic' => 'Wawasan Nusantara', 'difficulty' => 'mudah'])
        ->assertOk()
        ->assertJsonPath('changed', 2);

    $this->actingAs($this->teacher)->patchJson(route('question-bank.labels'), ['ids' => [$item->id], 'difficulty' => 'sulit'])->assertOk();

    expect(QuestionBankItem::query()->where('search_text', 'like', '%ibu kota%')->get()->map->only(QuestionBank::LABELS)->unique()->values()->all())
        ->toBe([['subject' => 'IPS', 'grade' => '8', 'topic' => 'Wawasan Nusantara', 'difficulty' => 'sulit']])
        ->and(QuestionBankItem::query()->where('source_question_id', '2')->sole()->difficulty)->toBeNull();

    $this->actingAs($this->teacher)->patchJson(route('question-bank.labels'), ['ids' => [$item->id], 'difficulty' => 'gampang'])->assertUnprocessable();
    $this->actingAs($this->teacher)->patchJson(route('question-bank.labels'), ['ids' => [$item->id], 'topic' => ''])->assertOk();

    expect($item->refresh()->topic)->toBeNull()->and($item->subject)->toBe('IPS');
});

test('a labelled question keeps its labels when edited, and brings them along when reused', function () {
    saveExam($this->exam, [bankQuestion(1, 'Ibu kota Indonesia?')]);
    $item = QuestionBankItem::query()->sole();
    $this->actingAs($this->teacher)->patchJson(route('question-bank.labels'), ['ids' => [$item->id], 'subject' => 'IPS', 'difficulty' => 'sedang']);

    saveExam($this->exam, [bankQuestion(1, 'Ibu kota negara Indonesia adalah?')]);
    expect($item->refresh())->subject->toBe('IPS')->difficulty->toBe('sedang');

    // Reused in another exam (from the bank): the new copy has the same labels.
    $question = $this->actingAs($this->teacher)->postJson(route('question-bank.questions'), ['ids' => [$item->id]])->json('questions.0');
    $nextExam = QuizForm::factory()->for($this->teacher)->create();
    saveExam($nextExam, [['id' => 9, ...$question]]);

    expect(QuestionBankItem::query()->where('quiz_form_id', $nextExam->id)->sole())
        ->subject->toBe('IPS')
        ->difficulty->toBe('sedang');
});

test('the bank is filtered by labels and archived questions are set aside', function () {
    saveExam($this->exam, [
        bankQuestion(1, 'Soal mudah IPA'),
        bankQuestion(2, 'Soal sulit IPA'),
        bankQuestion(3, 'Soal tanpa label'),
    ]);
    $items = QuestionBankItem::query()->orderBy('source_question_id')->get();
    $this->actingAs($this->teacher)->patchJson(route('question-bank.labels'), ['ids' => [$items[0]->id], 'subject' => 'IPA', 'difficulty' => 'mudah']);
    $this->actingAs($this->teacher)->patchJson(route('question-bank.labels'), ['ids' => [$items[1]->id], 'subject' => 'IPA', 'difficulty' => 'sulit']);

    $titles = fn (array $query) => collect($this->actingAs($this->teacher)->getJson(route('question-bank.index', $query))->json('items'))->pluck('title')->sort()->values()->all();

    expect($titles(['difficulty' => 'mudah']))->toBe(['Soal mudah IPA'])
        ->and($titles(['subject' => 'IPA']))->toBe(['Soal mudah IPA', 'Soal sulit IPA'])
        ->and($titles(['difficulty' => 'none']))->toBe(['Soal tanpa label']);

    $this->actingAs($this->teacher)->patchJson(route('question-bank.archive'), ['ids' => [$items[2]->id], 'archived' => true])->assertOk();

    expect($titles([]))->toBe(['Soal mudah IPA', 'Soal sulit IPA'])
        ->and($titles(['archived' => 1]))->toBe(['Soal tanpa label']);

    // Saving the exam again does not bring an archived question back.
    saveExam($this->exam, [bankQuestion(1, 'Soal mudah IPA'), bankQuestion(2, 'Soal sulit IPA'), bankQuestion(3, 'Soal tanpa label')]);
    expect($titles([]))->toBe(['Soal mudah IPA', 'Soal sulit IPA']);

    $this->actingAs($this->teacher)->patchJson(route('question-bank.archive'), ['ids' => [$items[2]->id], 'archived' => false])->assertOk();
    expect($titles([]))->toHaveCount(3)
        ->and($this->actingAs($this->teacher)->getJson(route('question-bank.index'))->json('suggestions.subjects'))->toBe(['IPA']);
});

test('a teacher cannot label or archive the questions of another teacher', function () {
    saveExam($this->exam, [bankQuestion(1, 'Ibu kota Indonesia?')]);
    $item = QuestionBankItem::query()->sole();
    $intruder = User::factory()->create(['role' => 'guru']);

    $this->actingAs($intruder)->patchJson(route('question-bank.labels'), ['ids' => [$item->id], 'difficulty' => 'sulit'])->assertJsonPath('changed', 0);
    $this->actingAs($intruder)->patchJson(route('question-bank.archive'), ['ids' => [$item->id], 'archived' => true])->assertJsonPath('changed', 0);

    expect($item->refresh())->difficulty->toBeNull()->archived_at->toBeNull();
});

test('a question is labelled from the editor, also by a collaborator, and the editor shows the labels', function () {
    saveExam($this->exam, [bankQuestion(1, 'Ibu kota Indonesia?'), bankQuestion(2, 'Untitled Question')]);
    $collaborator = User::factory()->create(['role' => 'guru']);
    $this->exam->collaborators()->attach($collaborator->id, ['role' => 'editor']);
    $url = fn (string $questionId) => route('forms.questions.labels', ['quizForm' => $this->exam->id, 'questionId' => $questionId]);

    $this->actingAs($collaborator)
        ->patchJson($url('1'), ['difficulty' => 'sedang', 'topic' => 'Ibu kota'])
        ->assertOk()
        ->assertJsonPath('labels.difficulty', 'sedang');

    // A new, still empty question is not in the bank yet.
    $this->actingAs($this->teacher)->patchJson($url('2'), ['difficulty' => 'mudah'])->assertUnprocessable();
    $this->actingAs(User::factory()->create(['role' => 'guru']))->patchJson($url('1'), ['difficulty' => 'mudah'])->assertForbidden();

    expect(QuestionBankItem::query()->sole()->user_id)->toBe($this->teacher->id);

    $this->actingAs($this->teacher)
        ->get(route('forms.edit', $this->exam->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('quizForm.questionLabels.1.difficulty', 'sedang')
            ->where('quizForm.questionLabels.1.topic', 'Ibu kota')
            ->where('quizForm.labelSuggestions.topics', ['Ibu kota']));
});

test('labels never reach the students', function () {
    $this->exam->update(['published_at' => now()]);
    saveExam($this->exam, [bankQuestion(1, 'Ibu kota Indonesia?')]);
    $this->actingAs($this->teacher)->patchJson(route('question-bank.labels'), ['ids' => [QuestionBankItem::query()->sole()->id], 'difficulty' => 'mudah', 'topic' => 'Kunci rahasia']);
    $this->exam->update(['published_at' => now()]);
    $student = User::factory()->create(['role' => 'siswa']);

    $token = $this->actingAs($student)->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');
    $start = $this->actingAs($student)->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token]);

    expect($start->getContent())->not->toContain('Kunci rahasia')->not->toContain('mudah')
        ->and($start->json('questions.0'))->not->toHaveKey('answer');
});

test('a new exam is made from the chosen bank questions', function () {
    saveExam($this->exam, [bankQuestion(1, 'Ibu kota Indonesia?'), bankQuestion(2, 'Lambang negara?', ['answer' => 1])]);
    $items = QuestionBankItem::query()->orderBy('source_question_id')->get();
    $foreign = QuestionBankItem::factory()->for(User::factory()->create(['role' => 'guru']))->create();

    $response = $this->actingAs($this->teacher)->post(route('question-bank.exam'), [
        'ids' => [$items[1]->id, $foreign->id, $items[0]->id],
        'title' => 'Remedial IPS',
    ]);

    $exam = QuizForm::query()->where('title', 'Remedial IPS')->sole();
    $response->assertRedirect(route('forms.edit', $exam->slug));

    expect($exam->user_id)->toBe($this->teacher->id)
        ->and($exam->published_at)->toBeNull()
        ->and(array_column($exam->questions, 'title'))->toBe(['Lambang negara?', 'Ibu kota Indonesia?'])
        ->and(array_column($exam->questions, 'id'))->toBe([1, 2])
        ->and($exam->questions[0]['answer'])->toBe(1);

    $this->actingAs(User::factory()->create(['role' => 'siswa']))->post(route('question-bank.exam'), ['ids' => [$items[0]->id]])->assertForbidden();
});

test('the bank page opens for teachers only', function () {
    $this->actingAs($this->teacher)->get(route('question-bank.page'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('QuestionBank/Index'));

    $this->actingAs(User::factory()->create(['role' => 'siswa']))->get(route('question-bank.page'))->assertForbidden();
});
