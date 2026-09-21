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

test('reporting: teacher owner and collaborators can export quiz gradebook as csv', function () {
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

    // Owner exports CSV
    $ownerResp = $this->actingAs($owner)->get(route('forms.responses.export', $quizForm));
    $ownerResp->assertOk();
    $ownerResp->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($ownerResp->headers->get('Content-Disposition'))->toContain('rekap_nilai_ujian-akhir-matematika');

    // Collaborator exports CSV
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
            ['id' => 1, 'title' => 'Soal 1', 'points' => 20],
            ['id' => 2, 'title' => 'Soal 2', 'points' => 30],
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
