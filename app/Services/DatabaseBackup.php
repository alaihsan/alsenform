<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

/**
 * Copies of the database in storage/app/backups (see config/maintenance.php): made with pg_dump
 * (PostgreSQL), mysqldump (MySQL/MariaDB) or as a copy of the file (SQLite), the newest kept.
 */
class DatabaseBackup
{
    /**
     * Every copy starts with this, then the time it was made: falcon-forms-20261028-080000.dump.
     */
    public const PREFIX = 'falcon-forms-';

    /**
     * Make a copy of the database now and remove the oldest beyond the number kept.
     *
     * @return array{path: string, name: string, size: int, created_at: CarbonImmutable}
     *
     * @throws RuntimeException With what went wrong and how to fix it, in Indonesian.
     */
    public function run(): array
    {
        $connection = config('database.default');
        $settings = (array) config("database.connections.{$connection}");
        $driver = (string) ($settings['driver'] ?? '');
        $directory = $this->directory();

        File::ensureDirectoryExists($directory, 0750);

        $name = self::PREFIX.now()->format('Ymd-His').$this->extension($driver);
        $path = $directory.DIRECTORY_SEPARATOR.$name;
        // Written under another name first: a copy cut short is never taken for a good one.
        $partial = $path.'.partial';

        try {
            match ($driver) {
                'pgsql' => $this->dump([(string) config('maintenance.backup.pg_dump'), '--format=custom', '--no-owner', '--no-privileges', ...$this->hostArguments($settings, '--username'), '--file', $partial, (string) $settings['database']], ['PGPASSWORD' => (string) ($settings['password'] ?? '')], 'pg_dump'),
                'mysql', 'mariadb' => $this->dump([(string) config('maintenance.backup.mysqldump'), '--single-transaction', '--routines', '--no-tablespaces', ...$this->hostArguments($settings, '--user'), '--result-file='.$partial, (string) $settings['database']], ['MYSQL_PWD' => (string) ($settings['password'] ?? '')], 'mysqldump'),
                'sqlite' => $this->copySqlite((string) $settings['database'], $partial),
                default => throw new RuntimeException("Backup otomatis belum mendukung database {$driver}."),
            };

            if (! is_file($partial) || filesize($partial) === 0) {
                throw new RuntimeException('Backup database kosong: tidak ada yang tersimpan.');
            }

            rename($partial, $path);
            // Password hashes and students' answers: for the server's own account only.
            @chmod($path, 0640);
        } finally {
            if (is_file($partial)) {
                @unlink($partial);
            }
        }

        $this->prune((int) config('maintenance.backup.keep'));

        return $this->describe($path);
    }

    /**
     * The newest copy, or null when there is none yet.
     *
     * @return array{path: string, name: string, size: int, created_at: CarbonImmutable}|null
     */
    public function latest(): ?array
    {
        return $this->all()[0] ?? null;
    }

    /**
     * Every copy, newest first.
     *
     * @return list<array{path: string, name: string, size: int, created_at: CarbonImmutable}>
     */
    public function all(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.self::PREFIX.'*') ?: [];
        $files = array_values(array_filter($files, fn (string $file): bool => is_file($file) && ! str_ends_with($file, '.partial')));

        $backups = array_map(fn (string $file): array => $this->describe($file), $files);
        usort($backups, fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at'] ?: strcmp($b['name'], $a['name']));

        return $backups;
    }

    /**
     * Whether the newest copy is older than the given hours (or there is none).
     */
    public function isOlderThan(float $hours): bool
    {
        $latest = $this->latest();

        return $latest === null || $latest['created_at']->lt(now()->subMinutes((int) round($hours * 60)));
    }

    /**
     * Keep only the newest copies.
     */
    public function prune(int $keep): void
    {
        foreach (array_slice($this->all(), max(1, $keep)) as $backup) {
            @unlink($backup['path']);
        }
    }

    public function directory(): string
    {
        return rtrim((string) config('maintenance.backup.path'), DIRECTORY_SEPARATOR);
    }

    /**
     * How to put a copy back, for the person who needs it.
     */
    public function restoreHint(string $path): string
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        return match ((string) config("database.connections.{$connection}.driver")) {
            'pgsql' => "pg_restore --clean --if-exists --no-owner -d {$database} ".escapeshellarg($path),
            'mysql', 'mariadb' => "mysql {$database} < ".escapeshellarg($path),
            default => 'Salin berkas itu ke '.$database.' saat server berhenti.',
        };
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    protected function dump(array $command, array $environment, string $tool): void
    {
        $process = new Process($command, base_path(), $environment, null, 600);

        try {
            $process->run();
        } catch (ProcessStartFailedException) {
            throw new RuntimeException("{$tool} tidak dapat dijalankan. Pasang {$tool}, atau isi path lengkapnya di .env (".($tool === 'pg_dump' ? 'BACKUP_PG_DUMP' : 'BACKUP_MYSQLDUMP').').');
        }

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException(match (true) {
                $process->getExitCode() === 127 || str_contains($error, 'not found') => "{$tool} tidak ditemukan. Pasang {$tool}, atau isi path lengkapnya di .env (".($tool === 'pg_dump' ? 'BACKUP_PG_DUMP, misalnya /Applications/Postgres.app/Contents/Versions/latest/bin/pg_dump' : 'BACKUP_MYSQLDUMP').').',
                str_contains($error, 'version mismatch') => "{$tool} lebih lama daripada server database: pakai {$tool} dari versi PostgreSQL yang sama (BACKUP_PG_DUMP di .env). {$error}",
                default => "{$tool} gagal: ".mb_strimwidth($error, 0, 500, '…'),
            });
        }
    }

    /**
     * A consistent copy of an SQLite database, also while it is in use.
     */
    protected function copySqlite(string $database, string $target): void
    {
        if ($database === '' || $database === ':memory:' || ! is_file($database)) {
            throw new RuntimeException('Berkas database SQLite tidak ditemukan.');
        }

        DB::statement('VACUUM INTO ?', [$target]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    protected function hostArguments(array $settings, string $userOption): array
    {
        $arguments = [];

        foreach (['--host' => $settings['host'] ?? null, '--port' => $settings['port'] ?? null, $userOption => $settings['username'] ?? null] as $option => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $arguments[] = $option.'='.$value;
            }
        }

        return $arguments;
    }

    protected function extension(string $driver): string
    {
        return match ($driver) {
            'pgsql' => '.dump',
            'sqlite' => '.sqlite',
            default => '.sql',
        };
    }

    /**
     * @return array{path: string, name: string, size: int, created_at: CarbonImmutable}
     */
    protected function describe(string $path): array
    {
        $name = basename($path);
        // The time is in the name; the file's own time changes when it is copied elsewhere.
        $createdAt = preg_match('/(\d{8}-\d{6})/', $name, $matches)
            ? CarbonImmutable::createFromFormat('Ymd-His', $matches[1], config('app.timezone'))
            : null;

        return [
            'path' => $path,
            'name' => $name,
            'size' => (int) @filesize($path),
            'created_at' => $createdAt instanceof CarbonImmutable ? $createdAt : CarbonImmutable::createFromTimestamp((int) @filemtime($path)),
        ];
    }
}
