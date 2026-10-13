<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\Addons;
use App\Services\QuestionBank;
use App\Support\Addon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A multiple choice question worth one point whose answer is "a".
 *
 * @return array<string, mixed>
 */
function remedialQuestion(int $id, string $title): array
{
    return ['id' => $id, 'title' => $title, 'type' => 'Multiple choice', 'options' => ['a', 'b'], 'answer' => 'a', 'points' => 1];
}

/**
 * Answers to questions 1 to 4: right ("a") for the given ids, wrong ("b") for the others.
 *
 * @param  list<int>  $right
 * @return array<int, string>
 */
function answersRightOn(array $right): array
{
    return collect(range(1, 4))->mapWithKeys(fn (int $id): array => [$id => in_array($id, $right, true) ? 'a' : 'b'])->all();
}

/**
 * The XML of a sheet of the exam's Excel workbook.
 */
function remedialWorkbookSheet(QuizForm $exam, User $as, int $sheet): string
{
    $export = test()->actingAs($as)->get(route('forms.responses.export', $exam));
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    $xml = (string) $zip->getFromName("xl/worksheets/sheet{$sheet}.xml").(string) $zip->getFromName('xl/workbook.xml');
    $zip->close();

    return $xml;
}

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'Ulangan Bab 3',
        'published_at' => now(),
        'questions' => [remedialQuestion(1, 'Soal satu'), remedialQuestion(2, 'Soal dua'), remedialQuestion(3, 'Soal tiga'), remedialQuestion(4, 'Soal empat')],
        'settings' => ['isQuiz' => true, 'timeLimit' => 40, 'lockOnBlur' => true, 'questionDraw' => ['enabled' => true, 'mode' => 'count', 'count' => 4]],
    ]);
    app(Addons::class)->install(Addon::Remedial, $this->teacher);

    [$this->ani, $this->budi, $this->citra, $this->dodi] = collect(['Ani', 'Budi', 'Citra', 'Dodi'])
        ->map(fn (string $name) => User::factory()->create(['role' => 'siswa', 'name' => $name, 'kelas' => 'X-1']))
        ->all();

    $respond = fn (?User $student, array $right, array $extra = []) => QuizResponse::query()->create([
        'quiz_form_id' => $this->exam->id,
        'user_id' => $student?->id,
        'respondent_identifier' => $student ? 'user_'.$student->id : 'anon_eka',
        'answers' => answersRightOn($right),
        'score' => count($right),
        ...$extra,
    ]);

    $respond($this->ani, [1, 2, 3, 4]);   // 100
    $respond($this->budi, [1, 2]);        // 50: wrong on 3 and 4
    $respond($this->citra, [1]);          // 25, then 75 in a second attempt
    $respond($this->citra, [1, 2, 3]);
    $respond($this->dodi, [1, 3]);        // 50: wrong on 2 and 4
    $respond(null, [], ['respondent_name' => 'Eka', 'respondent_class' => 'X-1']); // a guest, 0
});

test('the students are split by the KKM using their best attempt', function () {
    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.remedial.kkm', 75)
            ->has('quizForm.responses.remedial.students', 5)
            ->where('quizForm.responses.remedial.students.0.name', 'Ani')
            ->where('quizForm.responses.remedial.students.0.passed', true)
            ->where('quizForm.responses.remedial.students.1.name', 'Budi')
            ->where('quizForm.responses.remedial.students.1.grade', 50)
            ->where('quizForm.responses.remedial.students.1.passed', false)
            ->where('quizForm.responses.remedial.students.1.wrong', ['3', '4'])
            ->where('quizForm.responses.remedial.students.2.name', 'Citra')
            ->where('quizForm.responses.remedial.students.2.grade', 75)
            ->where('quizForm.responses.remedial.students.2.attempts', 2)
            ->where('quizForm.responses.remedial.students.2.passed', true)
            ->where('quizForm.responses.remedial.students.4.name', 'Eka')
            ->where('quizForm.responses.remedial.students.4.hasAccount', false)
            ->where('quizForm.responses.remedial.followUps', []));
});

test('switched off for the exam, there is no panel, no Excel sheet and no follow-up exam', function () {
    $this->exam->update(['settings' => [...$this->exam->settings, 'addons' => ['remedial' => false]]]);
    app()->forgetScopedInstances();

    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.responses.remedial', null));
    expect(remedialWorkbookSheet($this->exam, $this->teacher, 3))->not->toContain('Remedial &amp; Pengayaan');

    $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [
        'kind' => 'remedial', 'students' => ['user:'.$this->budi->id], 'source' => 'wrong',
    ])->assertForbidden();
});

test('a remedial exam is made of the questions the chosen students answered wrong, only for them', function () {
    $response = $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [
        'kind' => 'remedial',
        'students' => ['user:'.$this->budi->id, 'user:'.$this->dodi->id],
        'source' => 'wrong',
    ])->assertCreated();

    $remedial = QuizForm::query()->findOrFail($response->json('id'));

    expect($remedial->follow_up_of_id)->toBe($this->exam->id)
        ->and($remedial->follow_up_kind)->toBe('remedial')
        ->and($remedial->user_id)->toBe($this->teacher->id)
        ->and($remedial->published_at)->toBeNull()
        ->and($remedial->title)->toBe('Remedial: Ulangan Bab 3')
        ->and(array_column($remedial->questions, 'title'))->toBe(['Soal dua', 'Soal tiga', 'Soal empat'])
        ->and(array_column($remedial->questions, 'id'))->toBe([1, 2, 3])
        ->and($remedial->settings)->toHaveKey('timeLimit', 40)
        ->and($remedial->settings)->not->toHaveKey('questionDraw')
        ->and($remedial->participants()->pluck('users.id')->sort()->values()->all())->toBe([$this->budi->id, $this->dodi->id]);

    expect($response->json('editUrl'))->toBe(route('forms.edit', ['quizForm' => $remedial->slug]));

    // The editor of the remedial exam says what it follows up and who takes it.
    $this->actingAs($this->teacher)->get(route('forms.edit', $remedial))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.followUp.kind', 'remedial')
            ->where('quizForm.followUp.of.title', 'Ulangan Bab 3')
            ->has('quizForm.followUp.participants', 2));

    // The panel of the exam lists it, and marks the students chosen for it.
    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.remedial.followUps.0.kind', 'remedial')
            ->where('quizForm.responses.remedial.followUps.0.participants', 2)
            ->where('quizForm.responses.remedial.followUps.0.questionCount', 3)
            ->where('quizForm.responses.remedial.students.1.assignedTo', ['remedial']));
});

test('only the chosen students can open the remedial exam and find it on their dashboard', function () {
    $id = $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [
        'kind' => 'remedial', 'students' => ['user:'.$this->budi->id], 'source' => 'wrong',
    ])->json('id');
    $remedial = QuizForm::query()->findOrFail($id);
    $remedial->update(['published_at' => now()]);

    $this->actingAs($this->budi)->get(route('forms.public', $remedial->slug))
        ->assertInertia(fn (Assert $page) => $page->where('accessRestricted', false)->where('examSummary.questionCount', 2));
    $this->actingAs($this->ani)->get(route('forms.public', $remedial->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accessRestricted', true)
            ->where('restrictionReason', 'Ujian ini hanya untuk siswa yang dipilih guru (remedial atau pengayaan).'));
    $this->actingAs($this->ani)->postJson(route('forms.responses.start', $remedial->slug), ['session_token' => 'x'])->assertForbidden();

    $dashboardTitles = fn (User $student) => collect($this->actingAs($student)->get(route('dashboard'))->inertiaProps('recentForms'))->pluck('title')->all();
    expect($dashboardTitles($this->budi))->toContain('Remedial: Ulangan Bab 3')
        ->and($dashboardTitles($this->ani))->not->toContain('Remedial: Ulangan Bab 3')
        ->and($dashboardTitles($this->ani))->toContain('Ulangan Bab 3');
});

test('the remedial grade is kept apart; the grade that counts is at most the KKM', function () {
    $id = $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [
        'kind' => 'remedial', 'students' => ['user:'.$this->budi->id, 'user:'.$this->dodi->id], 'source' => 'wrong',
    ])->json('id');
    $remedial = QuizForm::query()->findOrFail($id);

    // Budi answers all three right (100), Dodi one of three (33,33).
    QuizResponse::query()->create(['quiz_form_id' => $remedial->id, 'user_id' => $this->budi->id, 'respondent_identifier' => 'user_'.$this->budi->id, 'answers' => [1 => 'a', 2 => 'a', 3 => 'a'], 'score' => 3]);
    QuizResponse::query()->create(['quiz_form_id' => $remedial->id, 'user_id' => $this->dodi->id, 'respondent_identifier' => 'user_'.$this->dodi->id, 'answers' => [1 => 'a', 2 => 'b', 3 => 'b'], 'score' => 1]);

    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.remedial.students.1.grade', 50)
            ->where('quizForm.responses.remedial.students.1.remedialGrade', 100)
            ->where('quizForm.responses.remedial.students.1.finalGrade', 75)
            ->where('quizForm.responses.remedial.students.3.remedialGrade', 33.33)
            ->where('quizForm.responses.remedial.students.3.finalGrade', 50)
            ->where('quizForm.responses.remedial.followUps.0.submitted', 2)
            // The original gradebook is unchanged.
            ->where('quizForm.responses.total', 6));

    $sheet = remedialWorkbookSheet($this->exam, $this->teacher, 3);
    expect($sheet)->toContain('Remedial &amp; Pengayaan')
        ->toContain('Tuntas setelah remedial')
        ->toContain('Belum tuntas setelah remedial')
        ->toContain('Belum mengikuti remedial');
});

test('a remedial exam can take other bank questions on the topics answered wrong', function () {
    $bank = app(QuestionBank::class);
    $bank->sync($this->exam);
    $bank->setLabelsOfQuestion($this->exam, '3', ['topic' => 'Pecahan']);
    $bank->setLabelsOfQuestion($this->exam, '4', ['topic' => 'Pecahan']);

    $request = fn () => $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [
        'kind' => 'remedial', 'students' => ['user:'.$this->budi->id], 'source' => 'topic',
    ]);

    $request()->assertUnprocessable()->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Belum ada soal lain setopik'));

    // Another exam of the teacher has three more questions on the topic, and one on another.
    $older = QuizForm::factory()->for($this->teacher)->create(['questions' => [
        remedialQuestion(1, 'Pecahan A'), remedialQuestion(2, 'Pecahan B'), remedialQuestion(3, 'Pecahan C'), remedialQuestion(4, 'Desimal A'),
    ]]);
    $bank->sync($older);
    foreach (['1', '2', '3'] as $questionId) {
        $bank->setLabelsOfQuestion($older, $questionId, ['topic' => 'Pecahan']);
    }
    $bank->setLabelsOfQuestion($older, '4', ['topic' => 'Desimal']);

    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.remedial.topicOf.3', 'Pecahan')
            ->where('quizForm.responses.remedial.bankPerTopic.Pecahan', 3));

    $remedial = QuizForm::query()->findOrFail($request()->assertCreated()->json('id'));

    // Budi got two Pecahan questions wrong: two other Pecahan questions.
    expect($remedial->questions)->toHaveCount(2)
        ->and(collect($remedial->questions)->pluck('title')->every(fn (string $title) => str_starts_with($title, 'Pecahan')))->toBeTrue();
});

test('an enrichment exam takes hard bank questions on the exam topics, for the students who passed', function () {
    $bank = app(QuestionBank::class);
    $bank->sync($this->exam);
    $bank->setLabelsOfQuestion($this->exam, '1', ['topic' => 'Pecahan']);
    $older = QuizForm::factory()->for($this->teacher)->create(['questions' => [remedialQuestion(1, 'Sulit A'), remedialQuestion(2, 'Mudah A'), remedialQuestion(3, 'Sedang A')]]);
    $bank->sync($older);
    $bank->setLabelsOfQuestion($older, '1', ['topic' => 'Pecahan', 'difficulty' => 'sulit']);
    $bank->setLabelsOfQuestion($older, '2', ['topic' => 'Pecahan', 'difficulty' => 'mudah']);
    $bank->setLabelsOfQuestion($older, '3', ['topic' => 'Pecahan', 'difficulty' => 'sedang']);

    $id = $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [
        'kind' => 'pengayaan', 'students' => ['user:'.$this->ani->id, 'user:'.$this->citra->id], 'source' => 'enrichment',
    ])->assertCreated()->json('id');
    $enrichment = QuizForm::query()->findOrFail($id);

    expect($enrichment->follow_up_kind)->toBe('pengayaan')
        ->and($enrichment->title)->toBe('Pengayaan: Ulangan Bab 3')
        ->and(array_column($enrichment->questions, 'title'))->toBe(['Sulit A', 'Sedang A'])
        ->and($enrichment->participants()->count())->toBe(2);

    // An empty one, to write the questions by hand.
    $empty = $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [
        'kind' => 'pengayaan', 'students' => ['user:'.$this->ani->id], 'source' => 'empty', 'title' => 'Tantangan Bab 3',
    ])->assertCreated()->json('id');
    expect(QuizForm::query()->findOrFail($empty))->title->toBe('Tantangan Bab 3')->questions->toBe([]);
});

test('with a guest among the chosen students the exam cannot be limited to them', function () {
    $guestKey = collect($this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.responses.remedial.students'))
        ->firstWhere('name', 'Eka')['key'];

    $response = $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [
        'kind' => 'remedial', 'students' => [$guestKey, 'user:'.$this->budi->id], 'source' => 'wrong',
    ])->assertCreated();

    expect(QuizForm::query()->findOrFail($response->json('id'))->participants()->count())->toBe(0)
        ->and($response->json('message'))->toContain('Siswa tamu tidak dapat dibatasi');
});

test('only the exam\'s teachers make follow-up exams, and a collaborator\'s belongs to the owner', function () {
    $payload = ['kind' => 'remedial', 'students' => ['user:'.$this->budi->id], 'source' => 'wrong'];

    $this->actingAs($this->budi)->postJson(route('forms.follow-ups.store', $this->exam), $payload)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'guru']))->postJson(route('forms.follow-ups.store', $this->exam), $payload)->assertForbidden();
    $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [...$payload, 'source' => 'enrichment'])->assertUnprocessable();
    $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [...$payload, 'students' => ['user:999999']])->assertUnprocessable();
    $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [...$payload, 'kind' => ['remedial']])->assertUnprocessable();

    $colleague = User::factory()->create(['role' => 'guru']);
    $this->exam->collaborators()->attach($colleague->id, ['role' => 'editor']);
    $id = $this->actingAs($colleague)->postJson(route('forms.follow-ups.store', $this->exam), $payload)->assertCreated()->json('id');
    $remedial = QuizForm::query()->findOrFail($id);

    expect($remedial->user_id)->toBe($this->teacher->id)
        ->and($remedial->canBeEditedBy($colleague))->toBeTrue();
});

test('a copy of a remedial exam is an exam of its own', function () {
    $id = $this->actingAs($this->teacher)->postJson(route('forms.follow-ups.store', $this->exam), [
        'kind' => 'remedial', 'students' => ['user:'.$this->budi->id], 'source' => 'wrong',
    ])->json('id');

    $this->actingAs($this->teacher)->post(route('forms.duplicate', $id))->assertRedirect();

    expect($this->exam->followUps()->count())->toBe(1)
        ->and(QuizForm::query()->latest('id')->first())->follow_up_of_id->toBeNull()->follow_up_kind->toBeNull();
});
