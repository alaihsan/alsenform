<?php

use App\Models\QuizForm;
use App\Models\ServerError;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Kesehatan Server: what to look at on the server before and during an exam, for admins.
 */

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->backups = storage_path('framework/testing/health-backups-'.uniqid());
    config(['maintenance.backup.path' => $this->backups, 'app.online_tunnel_metrics' => null]);
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
            ->has('health.checks', 11)
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

test('the cache shows where it runs, and whether the framework caches are built for an exam', function () {
    expect(healthChecks()['cache'])->toMatchArray(['label' => 'Cache', 'value' => 'Memori', 'tone' => 'good']);

    config(['cache.default' => 'failover']);
    Redis::shouldReceive('connection')->with('cache')->andReturn(Mockery::mock(['ping' => true]));
    $cache = healthChecks()['cache'];

    expect($cache)->toMatchArray(['value' => 'Redis', 'tone' => 'good', 'hint' => null])
        ->and($cache['detail'])->toEndWith(' ms · konfigurasi dan routing dibaca ulang tiap permintaan');

    app()['env'] = 'production';

    expect(healthChecks()['cache'])->toMatchArray(['value' => 'Redis', 'tone' => 'warn'])
        ->and(healthChecks()['cache']['hint'])->toContain('php artisan exam:optimize');
});

test('Redis that is off is worth a look behind the database, and needs fixing on its own', function () {
    // Nothing listens on port 1.
    config(['cache.default' => 'failover', 'database.redis.cache.port' => 1]);

    expect(healthChecks()['cache'])->toMatchArray([
        'value' => 'Redis mati',
        'detail' => 'sementara memakai database, lebih lambat',
        'tone' => 'warn',
    ])->and(healthChecks()['cache']['hint'])->toContain('brew services start redis');

    config(['cache.default' => 'redis']);

    expect(healthChecks()['cache'])->toMatchArray(['value' => 'Redis mati', 'tone' => 'bad'])
        ->and(healthChecks()['cache']['hint'])->toContain('login dan halaman ujian gagal dibuka');
});

test('the online address is read from the Cloudflare tunnel, and left out without one', function () {
    expect(healthChecks())->not->toHaveKey('online');

    config(['app.online_tunnel_metrics' => '127.0.0.1:20241']);
    Http::fake(['127.0.0.1:20241/quicktunnel' => Http::response(['hostname' => 'patent-merry-inclusive-casa.trycloudflare.com'])]);

    expect(healthChecks()['online'])->toMatchArray([
        'label' => 'Akses online',
        'value' => 'Menyala',
        'detail' => 'https://patent-merry-inclusive-casa.trycloudflare.com',
        'tone' => 'good',
    ]);
});

test('a stopped tunnel is worth a look, without stopping the page', function () {
    config(['app.online_tunnel_metrics' => '127.0.0.1:20241']);
    Http::fake(['127.0.0.1:20241/quicktunnel' => Http::response('', 503)]);

    expect(healthChecks()['online'])->toMatchArray(['value' => 'Tunnel mati', 'tone' => 'warn'])
        ->and(healthChecks()['online']['hint'])->toContain('com.alsenform.tunnel');
});
