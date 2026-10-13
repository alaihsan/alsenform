<?php

use App\Models\AnswerFile;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'maxUploadSize' => 2],
        'questions' => [
            ['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4'], 'answer' => '4', 'points' => 2],
            ['id' => 2, 'title' => 'Ibu kota Jawa Barat?', 'type' => 'Short answer', 'answer' => 'Bandung', 'points' => 2],
            ['id' => 3, 'title' => 'Jelaskan fotosintesis', 'type' => 'Paragraph', 'points' => 4],
            ['id' => 4, 'title' => 'Foto hasil percobaan', 'type' => 'File upload', 'points' => 2],
        ],
    ]);
    $this->student = User::factory()->create(['role' => 'siswa', 'name' => 'Rani', 'kelas' => 'VII-A']);
    $this->response = QuizResponse::query()->create([
        'quiz_form_id' => $this->exam->id, 'user_id' => $this->student->id, 'respondent_identifier' => 'user_'.$this->student->id,
        'answers' => [1 => '4', 2 => 'Kota Bandung', 3 => 'Tumbuhan membuat makanan dengan cahaya.'], 'score' => 2,
    ]);
});

test('an essay not graded yet leaves the grade provisional; graded, it counts', function () {
    $gradebook = fn () => $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.responses');

    // Right on 1, the key judges "Kota Bandung" wrong: 2 of 4 automatic points; essay and file wait.
    expect($gradebook())
        ->ungradedTotal->toBe(2)
        ->hasGradableQuestions->toBeTrue()
        ->and($gradebook()['gradebook'][0])->toMatchArray(['score' => 2, 'maxScore' => 4, 'percentage' => 50, 'ungraded' => 2]);

    // The teacher accepts the short answer, gives the essay 3 of 4 and the (missing) file 0.
    foreach ([[2, 2, null], [3, 3, 'Bagus, sebutkan juga klorofil.'], [4, 0, 'Berkas tidak dikirim.']] as [$question, $score, $feedback]) {
        $this->actingAs($this->teacher)->patchJson(route('forms.grading.update', [$this->exam, $this->response]), [
            'question_id' => $question, 'score' => $score, 'feedback' => $feedback,
        ])->assertOk();
    }

    expect($this->response->refresh())
        ->manual_scores->toBe(['2' => 2, '3' => 3, '4' => 0])
        ->feedback->toBe(['3' => 'Bagus, sebutkan juga klorofil.', '4' => 'Berkas tidak dikirim.']);

    // 2 + 2 + 3 + 0 of 2 + 2 + 4 + 2.
    expect($gradebook())
        ->ungradedTotal->toBe(0)
        ->and($gradebook()['gradebook'][0])->toMatchArray(['score' => 7, 'maxScore' => 10, 'percentage' => 70, 'ungraded' => 0]);
});

test('grades are checked: within the points, only questions graded by hand, only the exam\'s teachers', function () {
    $url = route('forms.grading.update', [$this->exam, $this->response]);

    $this->actingAs($this->teacher)->patchJson($url, ['question_id' => 3, 'score' => 5])->assertUnprocessable()->assertJsonPath('errors.score.0', 'Nilai paling besar 4.');
    $this->actingAs($this->teacher)->patchJson($url, ['question_id' => 1, 'score' => 1])->assertUnprocessable();
    $this->actingAs($this->student)->patchJson($url, ['question_id' => 3, 'score' => 1])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'guru']))->patchJson($url, ['question_id' => 3, 'score' => 1])->assertForbidden();

    // Half points, and taking a grade back.
    $this->actingAs($this->teacher)->patchJson($url, ['question_id' => 3, 'score' => 2.5])->assertOk()->assertJsonPath('score', 2.5);
    $this->actingAs($this->teacher)->patchJson($url, ['question_id' => 3, 'score' => null])->assertOk()->assertJsonPath('ungraded', 2);
    expect($this->response->refresh()->manual_scores)->toBeNull();
});

test('the grading list shows one question for every student, waiting answers first', function () {
    $other = QuizResponse::query()->create([
        'quiz_form_id' => $this->exam->id, 'respondent_identifier' => 'anon_1', 'respondent_name' => 'Agus',
        'answers' => [3 => 'Proses membuat glukosa.'], 'manual_scores' => [3 => 4], 'score' => 0,
    ]);

    $this->actingAs($this->teacher)->getJson(route('forms.grading.index', [$this->exam, 'question' => 3]))
        ->assertOk()
        ->assertJsonCount(3, 'questions')
        ->assertJsonPath('questions.1.id', 3)
        ->assertJsonPath('questions.1.waiting', 1)
        ->assertJsonPath('questions.1.graded', 1)
        ->assertJsonPath('question.points', 4)
        ->assertJsonPath('answers.0.name', 'Rani')
        ->assertJsonPath('answers.0.score', null)
        ->assertJsonPath('answers.1.name', 'Agus')
        ->assertJsonPath('answers.1.score', 4);

    // Short answers show what the key said.
    $this->actingAs($this->teacher)->getJson(route('forms.grading.index', [$this->exam, 'question' => 2]))
        ->assertJsonPath('question.answerKey', 'Bandung')
        ->assertJsonPath('answers.0.autoCorrect', false)
        ->assertJsonPath('questions.0.waiting', 0);
});

test('a student hands in a file; it is kept with the answers and only teachers can open it', function () {
    Storage::fake('local');
    $this->actingAs($this->student);
    $token = $this->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token])->assertOk();

    $upload = fn (UploadedFile $file, $question = '4') => $this->postJson(route('forms.answer-files.store', $this->exam->slug), [
        'session_token' => $token, 'question_id' => $question, 'file' => $file,
    ]);

    $upload(UploadedFile::fake()->create('virus.exe', 10))->assertUnprocessable()->assertJsonPath('errors.file.0', 'Jenis berkas tidak didukung. Kirim foto, PDF, Word, Excel atau PowerPoint.');
    $upload(UploadedFile::fake()->image('besar.jpg')->size(3000))->assertUnprocessable()->assertJsonPath('errors.file.0', 'Berkas terlalu besar: paling besar 2 MB.');
    $upload(UploadedFile::fake()->image('foto.jpg'), '3')->assertUnprocessable();

    $file = $upload(UploadedFile::fake()->image('percobaan.jpg', 640, 480))->assertCreated()->json('file');
    expect($file)->toMatchArray(['name' => 'percobaan.jpg', 'type' => 'image/jpeg']);

    // A file of someone else's attempt, or a made-up one, does not count.
    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $token,
        'answers' => [1 => '4', 4 => [['id' => $file['id'], 'name' => 'ganti-nama.exe'], ['id' => '9b2b9a3e-0000-4000-8000-000000000000'], 'bukan-uuid']],
    ])->assertSuccessful();

    $answer = QuizResponse::query()->latest('id')->first()->answers[4];
    expect($answer)->toBe([['id' => $file['id'], 'name' => 'percobaan.jpg', 'size' => $file['size'], 'type' => 'image/jpeg']]);

    $stored = AnswerFile::query()->findOrFail($file['id']);
    Storage::disk('local')->assertExists($stored->path);

    $this->actingAs($this->teacher)->get(route('forms.answer-files.show', [$this->exam, $stored]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($this->student)->get(route('forms.answer-files.show', [$this->exam, $stored]))->assertForbidden();

    // In the grading list, with a link to open it.
    // (Rani's second attempt: the first one, from the setup, has no file.)
    $this->actingAs($this->teacher)->getJson(route('forms.grading.index', [$this->exam, 'question' => 4]))
        ->assertJsonPath('answers.1.attempt', 2)
        ->assertJsonPath('answers.1.files.0.name', 'percobaan.jpg')
        ->assertJsonPath('answers.1.files.0.isImage', true)
        ->assertJsonPath('answers.0.files', []);
});

test('the Excel workbook shows the teacher\'s grade next to the answer', function () {
    $this->response->update(['manual_scores' => [3 => 3]]);

    $export = $this->actingAs($this->teacher)->get(route('forms.responses.export', $this->exam));
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    $answers = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
    $recap = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect($answers)->toContain('Nilai guru: 3/4')
        ->and($recap)->toContain('1 esai/berkas belum dinilai (nilai sementara)');
});
