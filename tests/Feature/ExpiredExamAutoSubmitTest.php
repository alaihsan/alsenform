<?php

use App\Actions\SubmitExpiredAttempts;
use App\Console\Commands\ServeLanCommand;
use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Carbon::setTestNow('2026-10-12 03:00:00');

    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['collectEmail' => false, 'showProgress' => true, 'shuffleQuestions' => false, 'timeLimit' => 30],
        'questions' => [
            ['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4'], 'answer' => 1, 'points' => 10, 'required' => true],
            ['id' => 2, 'title' => 'Ibu kota Indonesia?', 'type' => 'Short answer', 'answer' => 'Jakarta', 'points' => 10, 'required' => true],
        ],
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function attemptOf(QuizForm $quizForm, array $attributes = []): QuizSession
{
    return QuizSession::create(array_merge([
        'quiz_form_id' => $quizForm->id,
        'respondent_identifier' => 'anon_'.Str::random(16),
        'respondent_name' => 'Budi Santoso',
        'respondent_class' => '8B',
        'respondent_email' => 'budi@example.com',
        'session_token' => Str::random(40),
        'started_at' => now()->subMinutes(32),
        'expires_at' => now()->subMinutes(2),
        'is_locked' => false,
        'draft_answers' => [1 => '4', 2 => 'Jakarta'],
        'draft_saved_at' => now()->subMinutes(3),
    ], $attributes));
}

test('an exam whose time ran out with the page closed is handed in with the saved answers', function () {
    $attempt = attemptOf($this->exam, ['draft_answers' => [1 => '4', 2 => 'Bandung', 99 => 'removed question']]);

    $this->artisan('exam:submit-expired')
        ->expectsOutputToContain('1 ujian yang waktunya habis dikirim otomatis')
        ->assertSuccessful();

    $response = QuizResponse::query()->sole();

    expect($response)
        ->quiz_session_id->toBe($attempt->id)
        ->user_id->toBeNull()
        ->respondent_identifier->toBe($attempt->respondent_identifier)
        ->respondent_name->toBe('Budi Santoso')
        ->respondent_class->toBe('8B')
        ->email->toBe('budi@example.com')
        ->answers->toEqual([1 => '4', 2 => 'Bandung'])
        ->score->toBe(10)
        ->is_timeout->toBeTrue()
        ->is_auto_submitted->toBeTrue()
        ->and($response->created_at->toDateTimeString())->toBe('2026-10-12 02:58:00');

    expect($attempt->refresh())
        ->submitted_at->not->toBeNull()
        ->draft_answers->toBeNull();

    $log = ActivityLog::query()->sole();
    expect($log)
        ->event->toBe(ActivityLog::EXAM_SUBMITTED)
        ->quiz_form_id->toBe($this->exam->id)
        ->respondent_name->toBe('Budi Santoso')
        ->device_type->toBe('unknown')
        ->ip_address->toBeNull()
        ->and($log->meta)->toEqual(['timeout' => true, 'auto' => true])
        ->and($log->created_at->toDateTimeString())->toBe('2026-10-12 02:58:00');

    // Nothing is handed in twice.
    $this->artisan('exam:submit-expired')
        ->expectsOutputToContain('Tidak ada ujian')
        ->assertSuccessful();

    expect(QuizResponse::query()->count())->toBe(1);
});

test('the report lists the handed in exam on the device it was taken on', function () {
    $attempt = attemptOf($this->exam);
    ActivityLog::factory()->create([
        'event' => ActivityLog::EXAM_STARTED,
        'quiz_form_id' => $this->exam->id,
        'user_id' => null,
        'respondent_identifier' => $attempt->respondent_identifier,
        'ip_address' => '192.168.1.40',
        'user_agent' => 'Mozilla/5.0 (Linux; Android 13; SM-A536E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36',
    ]);

    app(SubmitExpiredAttempts::class)->handle();

    expect(ActivityLog::query()->where('event', ActivityLog::EXAM_SUBMITTED)->sole())
        ->device_type->toBe('mobile')
        ->ip_address->toBe('192.168.1.40');
});

test('an account is handed in under its own name and email', function () {
    $student = User::factory()->create(['role' => 'siswa', 'email' => 'ani@sekolah.test']);
    attemptOf($this->exam, [
        'user_id' => $student->id,
        'respondent_identifier' => 'user_'.$student->id,
        'respondent_name' => null,
        'respondent_class' => null,
        'respondent_email' => null,
    ]);

    expect(app(SubmitExpiredAttempts::class)->handle())->toBe(1);

    expect(QuizResponse::query()->sole())
        ->user_id->toBe($student->id)
        ->email->toBe('ani@sekolah.test')
        ->score->toBe(20)
        ->and(ActivityLog::query()->sole()->user_id)->toBe($student->id);
});

test('an attempt that answered nothing is handed in with no answers', function () {
    attemptOf($this->exam, ['draft_answers' => null]);

    app(SubmitExpiredAttempts::class)->handle();

    expect(QuizResponse::query()->sole())
        ->answers->toBe([])
        ->score->toBe(0);
});

test('only attempts whose time ran out a while ago and were not handed in are submitted', function () {
    attemptOf($this->exam, ['expires_at' => now()->subSeconds(30)]);
    attemptOf($this->exam, ['started_at' => null, 'expires_at' => null]);
    attemptOf($this->exam, ['expires_at' => null]);
    attemptOf($this->exam, ['submitted_at' => now()->subMinutes(5)]);
    attemptOf($this->exam, ['user_id' => $this->teacher->id, 'respondent_identifier' => 'user_'.$this->teacher->id]);
    $deletedExam = QuizForm::factory()->create(['published_at' => now()]);
    attemptOf($deletedExam);
    $deletedExam->delete();

    expect(app(SubmitExpiredAttempts::class)->handle())->toBe(0)
        ->and(QuizResponse::query()->count())->toBe(0);

    Carbon::setTestNow(now()->addMinute());

    expect(app(SubmitExpiredAttempts::class)->handle())->toBe(1);
});

test('answers already stored by the exam page are not handed in a second time', function () {
    $attempt = attemptOf($this->exam);
    QuizResponse::query()->create([
        'quiz_form_id' => $this->exam->id,
        'respondent_identifier' => $attempt->respondent_identifier,
        'answers' => [1 => '4'],
        'score' => 10,
    ]);

    expect(app(SubmitExpiredAttempts::class)->handle())->toBe(0)
        ->and(QuizResponse::query()->count())->toBe(1)
        ->and($attempt->refresh()->submitted_at)->not->toBeNull();
});

test('the answers on the device sent after the server handed in replace the saved ones', function (array $settings) {
    $this->exam->update(['settings' => array_merge($this->exam->settings, $settings)]);
    $attempt = attemptOf($this->exam, ['draft_answers' => [1 => '3', 2 => 'Jakarta']]);
    app(SubmitExpiredAttempts::class)->handle();

    // The page comes back online with what the student had on the device when the time ran out.
    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'respondent_identifier' => $attempt->respondent_identifier,
        'session_token' => $attempt->session_token,
        'answers' => [1 => '4', 2 => ''],
        'is_timeout' => true,
    ])->assertOk();

    expect(QuizResponse::query()->sole())
        ->answers->toEqual([1 => '4', 2 => 'Jakarta'])
        ->score->toBe(20)
        ->is_timeout->toBeTrue()
        ->is_auto_submitted->toBeFalse();
})->with([
    'more than one response allowed' => [[]],
    'one response per student' => [['limitOneResponse' => true]],
]);

test('another page cannot overwrite the answers handed in by the server', function () {
    $attempt = attemptOf($this->exam, ['draft_answers' => [1 => '3']]);
    app(SubmitExpiredAttempts::class)->handle();

    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'respondent_identifier' => $attempt->respondent_identifier,
        'session_token' => 'forged-token',
        'answers' => [1 => '4'],
        'is_timeout' => true,
    ]);

    expect(QuizResponse::query()->where('is_auto_submitted', true)->sole()->answers)->toEqual([1 => '3']);
});

test('the exam page can hand in an empty answer sheet when the time runs out', function () {
    $attempt = attemptOf($this->exam, ['expires_at' => now(), 'draft_answers' => null]);

    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'respondent_identifier' => $attempt->respondent_identifier,
        'session_token' => $attempt->session_token,
        'answers' => [],
        'is_timeout' => true,
    ])->assertOk();

    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'respondent_identifier' => 'anon_'.Str::random(16),
        'answers' => [],
        'is_timeout' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors('answers');

    expect(QuizResponse::query()->sole()->score)->toBe(0);
});

test('the teacher sees the handed in attempts as soon as the results are opened', function () {
    attemptOf($this->exam);

    $this->actingAs($this->teacher)
        ->get(route('forms.edit', $this->exam->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.total', 1)
            ->where('quizForm.responses.gradebook.0.name', 'Budi Santoso')
            ->where('quizForm.responses.gradebook.0.is_timeout', true)
            ->where('quizForm.responses.gradebook.0.is_auto_submitted', true));
});

test('the excel results mark the exams handed in by the server', function () {
    attemptOf($this->exam);

    $export = $this->actingAs($this->teacher)->get(route('forms.responses.export', $this->exam))->assertOk();
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    $recap = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect($recap)->toContain('Budi Santoso')->toContain('Waktu habis (dikirim otomatis)');
});

test('a locked student whose time ran out is handed in while the teacher watches the exam', function () {
    $this->exam->update(['settings' => array_merge($this->exam->settings, ['lockOnBlur' => true])]);
    attemptOf($this->exam, ['is_locked' => true, 'blur_count' => 1]);

    $this->actingAs($this->teacher)
        ->getJson(route('forms.unlock-requests.index', $this->exam))
        ->assertOk()
        ->assertJsonPath('locked_count', 0)
        ->assertJsonPath('requests.0.state', 'submitted');

    expect(QuizResponse::query()->sole()->is_auto_submitted)->toBeTrue()
        ->and(ActivityLog::query()->sole()->meta)->toEqual(['timeout' => true, 'auto' => true, 'locked' => true]);

    $this->actingAs($this->teacher)
        ->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page->where('activities.rows.0.note', 'Dikirim otomatis: waktu habis saat terkunci'));
});

test('lan:serve hands in the exams whose time ran out while it serves', function () {
    attemptOf($this->exam);

    $command = new class extends ServeLanCommand
    {
        protected float $networkCheckIntervalSeconds = 0.0;

        protected float $autoSubmitIntervalSeconds = 0.0;

        protected function detectNetworkInterfaces(): array
        {
            return ['Wi-Fi (en0)' => '192.168.1.20'];
        }

        public function watch(Process $process): void
        {
            $this->watchNetworkChanges($process, ['Wi-Fi (en0)' => '192.168.1.20'], '0.0.0.0', 8000);
        }
    };

    $buffer = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

    $process = Mockery::mock(Process::class);
    $process->shouldReceive('isRunning')->andReturn(true, true, false);

    $command->watch($process);

    expect($buffer->fetch())->toContain('1 ujian yang waktunya habis dikirim otomatis')
        ->and(QuizResponse::query()->sole()->is_auto_submitted)->toBeTrue();
});
