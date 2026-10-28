<?php

use App\Actions\SubmitExpiredAttempts;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSessionSnapshot;
use App\Models\User;
use App\Support\QuizFormPayloads;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Proof of what was handed in: a receipt for the student, kept with the answers, and copies of the
 * answers the server had during the exam, for when a student says their answers are gone.
 */

beforeEach(function () {
    $this->teacher = User::factory()->create();
    $this->student = User::factory()->create(['role' => 'siswa', 'nis' => '2024101', 'name' => 'Ani Lestari']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 60],
        'questions' => [
            ['id' => 1, 'title' => '<p>Ibu kota Indonesia?</p>', 'type' => 'Short answer', 'answer' => 'Jakarta', 'points' => 1],
            ['id' => 2, 'title' => 'Warna langit', 'type' => 'Multiple choice', 'options' => ['Biru', 'Merah'], 'answer' => 'Biru', 'points' => 1],
            ['id' => 3, 'title' => 'Pilih bilangan genap', 'type' => 'Checkboxes', 'options' => ['2', '3', '4'], 'answer' => ['2', '4'], 'points' => 1],
        ],
    ]);
});

test('handing in gives the student a receipt that matches the answers kept', function () {
    $attempt = startedAttempt($this->exam, $this->student, ['expires_at' => now()->addHour()]);

    $response = $this->actingAs($this->student)->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Jakarta', 2 => 'Biru', 3 => ['2', '4']],
        'submit_attempts' => 3,
    ])->assertOk();

    $stored = QuizResponse::query()->sole();

    expect($response->json('receipt'))->toMatch('/^[0-9A-F]{4}-[0-9A-F]{4}$/')
        ->and($response->json('receipt'))->toBe($stored->receiptCode())
        ->and($stored->submit_attempts)->toBe(3)
        // Read back from the database, the answers still match their receipt.
        ->and($stored->fresh()->hasIntactReceipt())->toBeTrue();

    // A retry of the same attempt (its answer was lost on weak Wi-Fi) gets the same receipt.
    $this->actingAs($this->student)->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Jakarta'],
    ])->assertOk()->assertJsonPath('receipt', $stored->receiptCode());

    expect(QuizResponse::query()->count())->toBe(1);
});

test('answers changed outside the application no longer match their receipt', function () {
    $attempt = startedAttempt($this->exam, $this->student, ['expires_at' => now()->addHour()]);
    $this->actingAs($this->student)->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Jakarta', 2 => 'Biru'],
    ])->assertOk();

    DB::table('quiz_responses')->update(['answers' => json_encode(['1' => 'Bandung', '2' => 'Biru'])]);
    $stored = QuizResponse::query()->sole();

    expect($stored->hasIntactReceipt())->toBeFalse();

    $row = app(QuizFormPayloads::class)->responses($this->exam->fresh())['gradebook'][0];
    expect($row['receipt'])->toBe($stored->receiptCode())
        ->and($row['receiptIntact'])->toBeFalse();
});

test('a response handed in by the server when the time ran out gets a receipt too', function () {
    startedAttempt($this->exam, $this->student, ['expires_at' => now()->subMinutes(5), 'draft_answers' => [1 => 'Jakarta']]);

    app(SubmitExpiredAttempts::class)->handle();

    $stored = QuizResponse::query()->sole();
    expect($stored->is_auto_submitted)->toBeTrue()
        ->and($stored->receiptCode())->not->toBeNull()
        ->and($stored->fresh()->hasIntactReceipt())->toBeTrue();
});

test('the server keeps a copy of the answers every five minutes during the exam', function () {
    Carbon::setTestNow('2026-10-28 08:00:00');
    $attempt = startedAttempt($this->exam, $this->student, ['expires_at' => now()->addHour()]);
    $saveDraft = fn (array $answers) => $this->actingAs($this->student)->postJson(route('forms.responses.draft', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => $answers,
    ])->assertOk();

    $saveDraft([1 => 'Jakarta']);
    Carbon::setTestNow('2026-10-28 08:02:00');
    $saveDraft([1 => 'Jakarta', 2 => 'Biru']);
    Carbon::setTestNow('2026-10-28 08:06:00');
    $saveDraft([1 => 'Jakarta', 2 => 'Biru', 3 => ['2']]);

    expect(QuizSessionSnapshot::query()->orderBy('id')->pluck('answered')->all())->toBe([1, 3]);

    $this->actingAs($this->student)->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Jakarta'],
    ])->assertOk();
    $stored = QuizResponse::query()->sole();

    $history = $this->actingAs($this->teacher)->getJson(route('forms.responses.history', [$this->exam, $stored]))->assertOk();

    $history->assertJsonPath('receipt', $stored->receiptCode())
        ->assertJsonPath('receiptIntact', true)
        ->assertJsonPath('final.answered', 1)
        ->assertJsonPath('final.answers.0', ['number' => 1, 'title' => 'Ibu kota Indonesia?', 'answer' => 'Jakarta'])
        ->assertJsonCount(2, 'snapshots')
        ->assertJsonPath('snapshots.0.time', '15:00:00')
        ->assertJsonPath('snapshots.1.answered', 3)
        ->assertJsonPath('snapshots.1.answers.2.answer', '2');
});

test('only the exam\'s teachers see the history of its answers', function () {
    $attempt = startedAttempt($this->exam, $this->student, ['expires_at' => now()->addHour()]);
    $this->actingAs($this->student)->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $attempt->session_token,
        'answers' => [1 => 'Jakarta'],
    ])->assertOk();
    $stored = QuizResponse::query()->sole();

    $this->actingAs(User::factory()->create())->getJson(route('forms.responses.history', [$this->exam, $stored]))->assertForbidden();
    $this->actingAs($this->student)->getJson(route('forms.responses.history', [$this->exam, $stored]))->assertForbidden();

    $otherExam = QuizForm::factory()->for($this->teacher)->create();
    $this->actingAs($this->teacher)->getJson(route('forms.responses.history', [$otherExam, $stored]))->assertNotFound();
});
