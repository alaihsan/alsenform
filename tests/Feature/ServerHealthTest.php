<?php

use App\Models\QuizForm;
use App\Models\ServerError;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Kesehatan Server: what to look at on the server before and during an exam, for admins.
 */

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->backups = storage_path('framework/testing/health-backups-'.uniqid());
    config(['maintenance.backup.path' => $this->backups]);
});

afterEach(fn () => File::deleteDirectory($this->backups));

/**
 * The checks of the page, by key.
 *
 * @return array<string, array<string, mixed>>
 */
function healthChecks(): array
{
    $checks = test()->actingAs(test()->admin)->get(route('server-health.index'))->assertOk()->inertiaProps('health.checks');

    return collect($checks)->keyBy('key')->all();
}

test('only admins open Kesehatan Server', function () {
    $this->actingAs(User::factory()->create())->get(route('server-health.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'siswa', 'nis' => '1']))->get(route('server-health.index'))->assertForbidden();

    $this->actingAs($this->admin)->get(route('server-health.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/ServerHealth')
            ->has('health.checks', 9)
            ->where('health.checks.0.key', 'database')
            ->where('health.checks.0.tone', 'good'));
});

test('a server without a recent backup needs fixing', function () {
    expect(healthChecks()['backup'])->toMatchArray(['value' => 'Belum ada', 'tone' => 'bad'])
        ->and($this->actingAs($this->admin)->get(route('server-health.index'))->inertiaProps('health.overall'))->toBe('bad');

    File::ensureDirectoryExists($this->backups);
    File::put($this->backups.'/falcon-forms-'.now()->subHours(2)->format('Ymd-His').'.dump', 'dump');

    expect(healthChecks()['backup'])->toMatchArray(['value' => '2 jam yang lalu', 'tone' => 'good']);
});

test('open server errors, pending database updates and unsaved answers show up', function () {
    ServerError::factory()->create();
    DB::table('migrations')->where('migration', '2026_10_28_115650_track_answer_delivery')->delete();
    $exam = QuizForm::factory()->create(['published_at' => now()]);
    startedAttempt($exam, attributes: ['last_seen_at' => now(), 'unsynced_since' => now()->subMinutes(3)]);
    startedAttempt($exam, attributes: ['last_seen_at' => now()]);

    $checks = healthChecks();

    expect($checks['server_errors'])->toMatchArray(['value' => '1 belum selesai', 'tone' => 'warn'])
        ->and($checks['migrations'])->toMatchArray(['value' => '1 pembaruan tertunda', 'tone' => 'bad'])
        ->and($checks['exams'])->toMatchArray(['value' => '2', 'tone' => 'warn'])
        ->and($checks['exams']['detail'])->toContain('1 siswa');
});
