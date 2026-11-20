<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\ServerError;
use App\Models\User;
use App\Support\ServerErrorAdvice;
use App\Support\ServerErrorRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;

/*
 * Galat Server: an error of the server is kept for the admin, counted once per fault, with the
 * reference code that its error page shows and its line in the log carries.
 */

beforeEach(function () {
    config(['app.debug' => false]);

    Route::middleware('web')->group(function (): void {
        Route::get('_test/crash/{id}', fn (string $id) => throw new RuntimeException("Jawaban {$id} tidak dapat disimpan password=rahasia"));
        Route::get('_test/forms/{quizForm:slug}/crash', fn (QuizForm $quizForm) => throw new LogicException('Soal rusak'));
    });
});

test('a server error is recorded with the reference code its error page shows', function () {
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '2024101']);

    $response = $this->actingAs($student)->get('/_test/crash/12')->assertInternalServerError();

    $error = ServerError::query()->sole();
    $occurrence = ActivityLog::query()->where('event', ActivityLog::SERVER_ERROR)->sole();

    expect($error->last_reference)->toMatch('/^ERR-[2-9A-HJ-NP-Z]{6}$/')
        ->and($error->occurrences)->toBe(1)
        ->and($error->exception)->toBe(RuntimeException::class)
        ->and($error->location)->toStartWith('tests/Feature/ServerErrorsTest.php:')
        // Passwords in a message are never kept.
        ->and($error->message)->toBe('Jawaban 12 tidak dapat disimpan password=***')
        ->and($error->last_url)->toBe('/_test/crash/12')
        ->and($error->last_user_id)->toBe($student->id)
        ->and($occurrence->user_id)->toBe($student->id)
        ->and($occurrence->meta)->toMatchArray(['reference' => $error->last_reference, 'server_error_id' => $error->id, 'status' => 500, 'path' => '/_test/crash/12']);

    $response->assertSee('Kode laporan')->assertSee($error->last_reference)->assertDontSee('rahasia');
});

test('the error page inside the app gets the reference code too', function () {
    $this->get('/_test/crash/1', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertInternalServerError()
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.reference', ServerError::query()->value('last_reference'));
});

test('the log line of the error carries its reference code', function () {
    $logFile = storage_path('logs/testing-server-errors.log');
    File::delete($logFile);
    config(['logging.default' => 'testing-errors', 'logging.channels.testing-errors' => ['driver' => 'single', 'path' => $logFile]]);

    try {
        $this->get('/_test/crash/7')->assertInternalServerError();

        expect(File::get($logFile))->toContain('"reference":"'.ServerError::query()->value('last_reference').'"');
    } finally {
        File::delete($logFile);
    }
});

test('the same fault is counted once, whatever ids its message names, and a solved fault that comes back opens again', function () {
    $this->get('/_test/crash/1');
    $this->get('/_test/crash/2');

    $error = ServerError::query()->sole();
    expect($error->occurrences)->toBe(2)
        ->and($error->message)->toContain('Jawaban 2');

    $error->update(['resolved_at' => now()]);
    $this->get('/_test/crash/3');

    expect($error->fresh()->occurrences)->toBe(3)
        ->and($error->fresh()->resolved_at)->toBeNull()
        ->and(ActivityLog::query()->where('event', ActivityLog::SERVER_ERROR)->count())->toBe(3);
});

test('an error on an exam is recorded with the exam', function () {
    $quizForm = QuizForm::factory()->create(['slug' => 'ujian-ipa']);

    $this->get('/_test/forms/ujian-ipa/crash')->assertInternalServerError();

    expect(ActivityLog::query()->where('event', ActivityLog::SERVER_ERROR)->sole()->quiz_form_id)->toBe($quizForm->id);
});

test('an error page still shows when the errors cannot be recorded', function () {
    Schema::drop('server_errors');

    $this->get('/_test/crash/1')->assertInternalServerError()->assertSee('Terjadi Kesalahan di Server')->assertSee('Kode laporan');
});

test('errors that are not faults of the server, such as a missing page, are not recorded', function () {
    $this->get('/tidak-ada-halaman-ini')->assertNotFound();
    $this->post('/login', [])->assertSessionHasErrors();

    expect(ServerError::query()->count())->toBe(0);
});

test('only admins open Galat Server, which finds a fault by the code a student read', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $teacher = User::factory()->create();

    $this->get('/_test/crash/1');
    $this->get('/_test/crash/2');
    $firstReference = ActivityLog::query()->where('event', ActivityLog::SERVER_ERROR)->orderBy('id')->first()->meta['reference'];
    $other = ServerError::factory()->create(['message' => 'Berkas Word rusak']);

    $this->actingAs($teacher)->get(route('server-errors.index'))->assertForbidden();

    $this->actingAs($admin)->get(route('server-errors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/ServerErrors')
            ->where('summary.open', 2)
            ->where('summary.lastHour', 2)
            ->has('errors.rows', 2));

    // The code of the first occurrence, not the latest one, still finds its fault.
    $this->actingAs($admin)->get(route('server-errors.index', ['search' => strtolower($firstReference)]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('errors.rows', 1)
            ->where('errors.rows.0.exception', 'RuntimeException')
            ->where('errors.rows.0.occurrences', 2));

    $this->actingAs($admin)->get(route('server-errors.index', ['search' => 'berkas word']))
        ->assertInertia(fn (Assert $page) => $page->has('errors.rows', 1)->where('errors.rows.0.id', $other->id));
});

test('an admin sees who an error happened to, and marks it as solved', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $student = User::factory()->create(['name' => 'Ani Lestari', 'role' => 'siswa', 'nis' => '2024101']);

    $this->actingAs($student)->get('/_test/crash/1');
    $error = ServerError::query()->sole();

    $this->actingAs($admin)->getJson(route('server-errors.show', $error))
        ->assertOk()
        ->assertJsonPath('occurrences.0.person.name', 'Ani Lestari')
        ->assertJsonPath('occurrences.0.reference', $error->last_reference)
        ->assertJsonPath('occurrences.0.detail', 'GET /_test/crash/1');

    $this->actingAs($admin)->patch(route('server-errors.update', $error), ['resolved' => true])->assertRedirect();
    expect($error->fresh()->resolved_at)->not->toBeNull();

    $this->actingAs($admin)->get(route('server-errors.index'))->assertInertia(fn (Assert $page) => $page->where('summary.open', 0)->has('errors.rows', 0));
    $this->actingAs($admin)->get(route('server-errors.index', ['status' => 'resolved']))->assertInertia(fn (Assert $page) => $page->has('errors.rows', 1));

    $this->actingAs($admin)->patch(route('server-errors.update', $error), ['resolved' => false]);
    expect($error->fresh()->resolved_at)->toBeNull();
});

test('a student\'s server error shows in the teacher\'s activity report with its code, not its message', function () {
    $teacher = User::factory()->create();
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '2024101']);

    $this->actingAs($student)->get('/_test/crash/5');
    $reference = ServerError::query()->value('last_reference');

    $this->actingAs($teacher)->get(route('reports.index', ['event' => ActivityLog::SERVER_ERROR]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.rows', 1)
            ->where('activities.rows.0.eventLabel', 'Galat server')
            ->where('activities.rows.0.note', "{$reference}: galat server (HTTP 500)")
            ->where('activities.rows.0.detail', 'GET /_test/crash/5'));
});

test('known errors say what they mean and what to do when they happen again', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    ServerError::factory()->create([
        'exception' => QueryException::class,
        'message' => 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: alsenform, SQL: insert ignore into `cache` (`key`, `value`, `expiration`) values (alsen_form_cache_4f31:timer, i:1793944710;, 1793944710))',
        'location' => 'public/index.php:20',
        'last_method' => 'GET',
        'last_url' => '/forms/ujian-ipa/status?session_token=abc&returning=navigate&unsynced_since=0',
        'last_seen_at' => now()->subMinutes(3),
    ]);
    ServerError::factory()->create([
        'exception' => NamespaceNotFoundException::class,
        'message' => 'There are no commands defined in the "boost" namespace.',
        'location' => 'artisan:16',
        'last_method' => 'CLI',
        'last_url' => 'php artisan boost:mcp',
        'last_seen_at' => now()->subMinutes(2),
    ]);
    ServerError::factory()->create([
        'exception' => CommandNotFoundException::class,
        'message' => 'Command "pan" is not defined.',
        'location' => 'artisan:16',
        'last_method' => 'CLI',
        'last_url' => 'php artisan pan',
        'last_seen_at' => now()->subMinute(),
    ]);
    ServerError::factory()->create(['last_seen_at' => now()]);

    $this->actingAs($admin)->get(route('server-errors.index'))
        ->assertInertia(fn (Assert $page) => $page
            // A fault of the app's own code has no ready answer.
            ->where('errors.rows.0.advice', null)
            ->where('errors.rows.1.advice.title', 'Bukan gangguan aplikasi: perintah artisan itu tidak ada')
            ->where('errors.rows.1.advice.steps.0.command', 'php artisan list')
            ->where('errors.rows.2.advice.title', 'Bukan gangguan aplikasi: Laravel Boost mati di server produksi')
            ->where('errors.rows.2.advice.steps.0.command', 'php artisan boost:mcp --env=local')
            ->where('errors.rows.3.advice.title', 'Deadlock database: dua permintaan berebut data yang sama')
            ->where('errors.rows.3.advice.explanation', fn (string $explanation): bool => str_contains($explanation, 'otomatis hingga 3 kali')
                && str_contains($explanation, 'tidak ada jawaban yang hilang'))
            ->where('errors.rows.3.advice.steps.1.text', fn (string $text): bool => str_starts_with($text, 'Galat di tabel cache berarti cache sedang memakai database karena Redis mati'))
            ->where('errors.rows.3.advice.steps.1.command', 'brew services start redis'));
});

test('a deadlock on the sessions of another page is explained without the exam status', function () {
    $advice = ServerErrorAdvice::for(ServerError::factory()->make([
        'exception' => QueryException::class,
        'message' => 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction (Connection: mysql, SQL: update `sessions` set `payload` = YTo0, `last_activity` = 1762439193 where `id` = abc)',
        'last_url' => '/dashboard',
    ]));

    expect($advice['explanation'])->not->toContain('cek status ujian')
        ->and($advice['steps'][1]['text'])->toStartWith('Pastikan Redis menyala');
});

test('an error during a command is recorded without a person', function () {
    app(ServerErrorRecorder::class)->record(new RuntimeException('Backup gagal'));

    $error = ServerError::query()->sole();

    expect($error->last_method)->toBe('CLI')
        ->and($error->last_user_id)->toBeNull()
        ->and(ActivityLog::query()->count())->toBe(0);
});
