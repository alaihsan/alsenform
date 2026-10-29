<?php

use App\Console\Commands\ServeLanCommand;
use App\Models\ActivityLog;
use App\Models\ServerError;
use App\Services\DatabaseBackup;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/*
 * db:backup: a copy of the database in storage/app/backups, the newest kept, made by lan:serve
 * every day. pg_dump is replaced by a script that writes what it was asked, so the test does not
 * depend on the PostgreSQL tools of the machine.
 */

beforeEach(function () {
    $this->directory = storage_path('framework/testing/backups-'.uniqid());
    $this->tools = storage_path('framework/testing/tools-'.uniqid());
    File::ensureDirectoryExists($this->tools);

    $fakePgDump = $this->tools.'/pg_dump';
    File::put($fakePgDump, <<<'SH'
        #!/bin/sh
        out=""
        for arg in "$@"; do
            if [ "$take" = "1" ]; then out="$arg"; take=0; fi
            if [ "$arg" = "--file" ]; then take=1; fi
        done
        printf 'PGPASSWORD=%s\n%s\n' "$PGPASSWORD" "$*" > "$out"
        SH);
    chmod($fakePgDump, 0755);

    config([
        'maintenance.backup.path' => $this->directory,
        'maintenance.backup.pg_dump' => $fakePgDump,
        'maintenance.backup.keep' => 3,
        'database.connections.pgsql.password' => 'rahasia-db',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->directory);
    File::deleteDirectory($this->tools);
});

test('db:backup writes a copy of the database with pg_dump and says how to restore it', function () {
    Carbon::setTestNow('2026-10-28 01:00:00');

    $this->artisan('db:backup')
        ->expectsOutputToContain('Backup database tersimpan')
        ->expectsOutputToContain('pg_restore --clean --if-exists --no-owner -d '.config('database.connections.pgsql.database'))
        ->assertSuccessful();

    $backup = app(DatabaseBackup::class)->latest();
    $dump = File::get($backup['path']);

    expect($backup['name'])->toBe('falcon-forms-20261028-010000.dump')
        ->and($backup['created_at']->toDateTimeString())->toBe('2026-10-28 01:00:00')
        // The password goes in the environment, never on the command line.
        ->and($dump)->toStartWith('PGPASSWORD=rahasia-db')
        ->and($dump)->toContain('--format=custom')
        ->and($dump)->toContain('--host=')
        ->and($dump)->toContain(config('database.connections.pgsql.database'))
        ->and(substr_count($dump, 'rahasia-db'))->toBe(1);
});

test('only the newest copies are kept', function () {
    foreach (['2026-10-20 01:00:00', '2026-10-21 01:00:00', '2026-10-22 01:00:00', '2026-10-23 01:00:00'] as $time) {
        Carbon::setTestNow($time);
        app(DatabaseBackup::class)->run();
    }

    expect(array_column(app(DatabaseBackup::class)->all(), 'name'))->toBe([
        'falcon-forms-20261023-010000.dump',
        'falcon-forms-20261022-010000.dump',
        'falcon-forms-20261021-010000.dump',
    ]);
});

test('--if-older-than skips a backup while the latest is recent', function () {
    Carbon::setTestNow('2026-10-28 01:00:00');
    app(DatabaseBackup::class)->run();

    Carbon::setTestNow('2026-10-28 09:00:00');
    $this->artisan('db:backup --if-older-than=12')->expectsOutputToContain('tidak perlu dibuat lagi')->assertSuccessful();
    expect(app(DatabaseBackup::class)->all())->toHaveCount(1);

    Carbon::setTestNow('2026-10-28 14:00:00');
    $this->artisan('db:backup --if-older-than=12')->expectsOutputToContain('Backup database tersimpan')->assertSuccessful();
    expect(app(DatabaseBackup::class)->all())->toHaveCount(2);
});

test('a failed backup says how to fix it, leaves no half-written copy and is recorded for the admin', function () {
    config(['maintenance.backup.pg_dump' => $this->tools.'/tidak-ada/pg_dump']);

    expect(Artisan::call('db:backup'))->toBe(1)
        ->and(Artisan::output())->toContain('Backup database gagal: pg_dump tidak ditemukan')->toContain('BACKUP_PG_DUMP');

    expect(File::isDirectory($this->directory) ? File::files($this->directory) : [])->toBe([])
        ->and(ServerError::query()->sole()->last_method)->toBe('CLI');
});

test('the backup command is scheduled at night for servers run without lan:serve', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('db:backup --if-older-than=20')->toContain('model:prune');
});

test('lan:serve makes the day\'s backup and clears records past their retention once the latest copy is old', function () {
    $command = new class extends ServeLanCommand
    {
        public function maintain(float $hours): void
        {
            $this->runDailyMaintenance($hours);
        }
    };
    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));

    ActivityLog::factory()->create(['created_at' => now()->subDays(400)]);
    $recent = ActivityLog::factory()->create(['created_at' => now()->subDays(30)]);

    $command->maintain(12);

    expect($output->fetch())->toContain('Backup database tersimpan')
        ->and(app(DatabaseBackup::class)->all())->toHaveCount(1)
        ->and(ActivityLog::query()->pluck('id')->all())->toBe([$recent->id]);

    // Done for today: the next check makes no second copy.
    $command->maintain(12);
    expect(app(DatabaseBackup::class)->all())->toHaveCount(1);
});
