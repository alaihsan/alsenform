<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\UnlockRequest;
use App\Models\User;
use App\Support\QuizFormPayloads;

test('proctoring: tab blur lock records blur count and logs timestamps', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $student = User::factory()->create(['role' => 'siswa']);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $teacher->id,
        'slug' => 'test-proctoring-quiz',
        'published_at' => now(),
        'settings' => ['lockOnBlur' => true],
    ]);

    // First blur lock
    $resp1 = $this->actingAs($student)->postJson(route('forms.responses.lock', ['quizForm' => $quizForm->slug]));
    $resp1->assertOk()->assertJson(['locked' => true]);

    $session = QuizSession::where('quiz_form_id', $quizForm->id)
        ->where('respondent_identifier', 'user_'.$student->id)
        ->first();

    expect($session)->not->toBeNull()
        ->and($session->is_locked)->toBeTrue()
        ->and($session->blur_count)->toBe(1)
        ->and($session->blur_logs)->toHaveCount(1);

    // Second blur lock
    $resp2 = $this->actingAs($student)->postJson(route('forms.responses.lock', ['quizForm' => $quizForm->slug]));
    $resp2->assertOk();

    $session->refresh();
    expect($session->blur_count)->toBe(2)
        ->and($session->blur_logs)->toHaveCount(2);

    // Teacher views unlock requests
    UnlockRequest::create([
        'quiz_form_id' => $quizForm->id,
        'respondent_identifier' => 'user_'.$student->id,
        'email' => $student->email,
        'unlock_code' => 'hashed',
        'status' => 'pending',
    ]);

    $teacherResp = $this->actingAs($teacher)->getJson(route('forms.unlock-requests.index', $quizForm));
    $teacherResp->assertOk();
    $data = $teacherResp->json('requests');
    expect($data)->toHaveCount(1)
        ->and($data[0]['blur_count'])->toBe(2)
        ->and($data[0]['last_blur_at'])->not->toBeNull();
});

test('onboarding: student can update mandatory password and clear must_change_password flag', function () {
    $student = User::factory()->create([
        'role' => 'siswa',
        'must_change_password' => true,
        'password' => bcrypt('old-password-123'),
    ]);

    $response = $this->actingAs($student)->from(route('password.edit'))->put(route('password.update'), [
        'current_password' => 'old-password-123',
        'password' => 'new-secure-password-456',
        'password_confirmation' => 'new-secure-password-456',
    ]);

    $response->assertSessionHasNoErrors();
    $student->refresh();
    expect($student->must_change_password)->toBeFalse();
});

test('reporting: teacher owner and collaborators can export quiz results as excel', function () {
    $owner = User::factory()->create(['role' => 'guru']);
    $collaborator = User::factory()->create(['role' => 'guru']);
    $stranger = User::factory()->create(['role' => 'guru']);
    $student = User::factory()->create([
        'role' => 'siswa',
        'name' => 'Budi Santoso',
        'nis' => '20240101',
        'kelas' => '10-IPA-1',
    ]);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $owner->id,
        'title' => 'Ujian Akhir Matematika',
        'slug' => 'ujian-akhir-matematika',
        'published_at' => now(),
        'questions' => [
            ['id' => 1, 'title' => 'Q1', 'points' => 10],
            ['id' => 2, 'title' => 'Q2', 'points' => 15],
        ],
    ]);
    $quizForm->collaborators()->attach($collaborator->id);

    QuizResponse::create([
        'quiz_form_id' => $quizForm->id,
        'user_id' => $student->id,
        'respondent_identifier' => 'user_'.$student->id,
        'email' => $student->email,
        'score' => 25,
        'is_timeout' => false,
        'answers' => ['1' => 'A', '2' => 'B'],
    ]);

    // Owner downloads the Excel workbook
    $ownerResp = $this->actingAs($owner)->get(route('forms.responses.export', $quizForm));
    $ownerResp->assertOk();
    $ownerResp->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($ownerResp->headers->get('Content-Disposition'))->toContain('hasil_jawaban_ujian-akhir-matematika')->toContain('.xlsx');

    $zip = new ZipArchive;
    expect($zip->open($ownerResp->baseResponse->getFile()->getPathname()))->toBeTrue();
    expect($zip->getFromName('xl/workbook.xml'))->toContain('Rekap Nilai')->toContain('Jawaban Siswa')
        ->and($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('Budi Santoso')->toContain('20240101')->toContain('10-IPA-1');
    $zip->close();

    // Collaborator downloads it too
    $collabResp = $this->actingAs($collaborator)->get(route('forms.responses.export', $quizForm));
    $collabResp->assertOk();

    // Stranger teacher is forbidden
    $strangerResp = $this->actingAs($stranger)->get(route('forms.responses.export', $quizForm));
    $strangerResp->assertStatus(403);
});

test('reporting: payloads include gradebook and maxScore summary', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $student = User::factory()->create([
        'role' => 'siswa',
        'name' => 'Dewi Lestari',
        'nis' => '20240102',
        'kelas' => '10-IPA-2',
    ]);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $teacher->id,
        'slug' => 'payload-test-quiz',
        'published_at' => now(),
        'questions' => [
            ['id' => 1, 'title' => 'Soal 1', 'type' => 'Multiple choice', 'options' => ['Ans1', 'Lain'], 'answer' => 0, 'points' => 20],
            ['id' => 2, 'title' => 'Soal 2', 'type' => 'Short answer', 'answer' => 'Ans2', 'points' => 30],
        ],
    ]);

    QuizResponse::create([
        'quiz_form_id' => $quizForm->id,
        'user_id' => $student->id,
        'respondent_identifier' => 'user_'.$student->id,
        'email' => $student->email,
        'score' => 50,
        'is_timeout' => false,
        'answers' => ['1' => 'Ans1', '2' => 'Ans2'],
    ]);

    $payloads = app(QuizFormPayloads::class);
    $editorData = $payloads->editor($quizForm, true);

    expect($editorData['responses'])->not->toBeNull()
        ->and($editorData['responses']['total'])->toBe(1)
        ->and($editorData['responses']['maxScore'])->toBe(50)
        ->and($editorData['responses']['gradebook'])->toHaveCount(1)
        ->and($editorData['responses']['gradebook'][0]['name'])->toBe('Dewi Lestari')
        ->and($editorData['responses']['gradebook'][0]['nis'])->toBe('20240102')
        ->and($editorData['responses']['gradebook'][0]['kelas'])->toBe('10-IPA-2')
        ->and($editorData['responses']['gradebook'][0]['score'])->toBe(50)
        ->and($editorData['responses']['gradebook'][0]['percentage'])->toBe(100.0)
        ->and($editorData['responses']['gradebook'][0]['is_timeout'])->toBeFalse()
        ->and($editorData['exportResponsesUrl'])->toContain('responses/export');
});

test('reporting: the gradebook re-grades answers against the current answer key', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '20240103']);
    $quizForm = QuizForm::factory()->create([
        'user_id' => $teacher->id,
        'questions' => [
            ['id' => 1, 'title' => 'Ibu kota?', 'type' => 'Multiple choice', 'options' => ['Bandung', 'Jakarta'], 'answer' => 0],
            ['id' => 2, 'title' => '7 x 8', 'type' => 'Short answer', 'answer' => '56'],
            ['id' => 3, 'title' => 'Jelaskan', 'type' => 'Paragraph', 'answer' => ''],
        ],
    ]);
    // Scored 1 of 2 at submission, when the key of question 1 was wrong.
    QuizResponse::create([
        'quiz_form_id' => $quizForm->id,
        'user_id' => $student->id,
        'respondent_identifier' => 'user_'.$student->id,
        'score' => 1,
        'answers' => ['1' => 'Jakarta', '2' => '56', '3' => 'Uraian murid'],
    ]);

    $questions = $quizForm->questions;
    $questions[0]['answer'] = 1;
    $quizForm->update(['questions' => $questions]);

    $gradebook = app(QuizFormPayloads::class)->responses($quizForm->refresh());

    expect($gradebook['maxScore'])->toBe(2)
        ->and($gradebook['gradebook'][0]['score'])->toBe(2)
        ->and($gradebook['gradebook'][0]['percentage'])->toBe(100.0);
});

test('reporting: grid answers are summarised per row instead of breaking the editor', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $quizForm = QuizForm::factory()->create([
        'user_id' => $teacher->id,
        'questions' => [
            ['id' => 1, 'title' => 'Ciri hewan', 'type' => 'Tick box grid', 'rows' => ['Kucing', 'Ayam'], 'columns' => ['Menyusui', 'Bertelur'], 'answer' => ['0' => [0], '1' => [1]]],
            ['id' => 2, 'title' => 'Golongan', 'type' => 'Multiple-choice grid', 'rows' => ['Paus'], 'columns' => ['Mamalia', 'Ikan'], 'answer' => ['0' => 0]],
        ],
    ]);
    QuizResponse::create([
        'quiz_form_id' => $quizForm->id,
        'respondent_identifier' => 'guest_grid',
        'score' => 0,
        'answers' => ['1' => ['0' => [0, 1], '1' => [1]], '2' => ['0' => 0]],
    ]);

    $this->actingAs($teacher)->get(route('forms.edit', ['quizForm' => $quizForm->slug]))->assertOk();

    $summary = app(QuizFormPayloads::class)->responses($quizForm)['questions'];

    expect(collect($summary[0]['options'])->pluck('label')->all())->toEqualCanonicalizing(['Kucing → Menyusui', 'Kucing → Bertelur', 'Ayam → Bertelur'])
        ->and($summary[1]['options'][0]['label'])->toBe('Paus → Mamalia');
});
