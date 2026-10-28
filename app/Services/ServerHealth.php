<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\QuizSession;
use App\Models\ServerError;
use App\Support\AssetBuild;
use App\Support\DisplayTime;
use App\Support\PendingMigrations;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Kesehatan Server: what to look at on the server before (and during) an exam, each with whether
 * it is fine ("good"), worth a look ("warn") or needs fixing ("bad") and what to do.
 */
class ServerHealth
{
    /**
     * A page that has been in touch this recently counts as an exam being worked on now.
     */
    public const WORKING_WITHIN_SECONDS = 60;

    public function __construct(
        protected DatabaseBackup $backup,
        protected AssetBuild $assetBuild,
        protected PendingMigrations $pendingMigrations,
    ) {}

    /**
     * @return list<array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}>
     */
    public function checks(): array
    {
        return [
            $this->database(),
            $this->exams(),
            $this->serverErrors(),
            $this->backup(),
            $this->disk(),
            $this->migrations(),
            $this->build(),
            $this->environment(),
            $this->logs(),
        ];
    }

    /**
     * The worst tone of the checks: what the page says at the top.
     *
     * @param  list<array{tone: string}>  $checks
     */
    public function overall(array $checks): string
    {
        $tones = array_column($checks, 'tone');

        return in_array('bad', $tones, true) ? 'bad' : (in_array('warn', $tones, true) ? 'warn' : 'good');
    }

    /**
     * @return array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}
     */
    protected function database(): array
    {
        try {
            $started = hrtime(true);
            DB::select('select 1');
            $milliseconds = (hrtime(true) - $started) / 1_000_000;
        } catch (Throwable) {
            return $this->check('database', 'Database', 'Tidak terhubung', null, 'bad', 'Nyalakan layanan database (PostgreSQL/MySQL) dan periksa DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD di .env.');
        }

        $size = $this->databaseSize();

        return $this->check(
            'database',
            'Database',
            number_format($milliseconds, 1, ',', '.').' ms',
            trim(config('database.default').($size !== null ? ' · '.$this->bytes($size, 1) : '')),
            $milliseconds < 50 ? 'good' : ($milliseconds < 300 ? 'warn' : 'bad'),
            $milliseconds < 50 ? null : 'Database lambat menjawab: tutup aplikasi berat di komputer server, atau periksa disk yang hampir penuh.',
        );
    }

    /**
     * @return array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}
     */
    protected function exams(): array
    {
        $working = QuizSession::query()
            ->whereNotNull('started_at')
            ->whereNull('submitted_at')
            ->where('last_seen_at', '>=', now()->subSeconds(self::WORKING_WITHIN_SECONDS))
            ->count();
        $unsaved = QuizSession::query()
            ->whereNotNull('started_at')
            ->whereNull('submitted_at')
            ->where('unsynced_since', '<', now()->subSeconds(QuizSession::UNSAVED_AFTER_SECONDS))
            ->where('last_seen_at', '>=', now()->subSeconds(self::WORKING_WITHIN_SECONDS))
            ->count();

        return $this->check(
            'exams',
            'Siswa mengerjakan',
            (string) $working,
            $unsaved > 0 ? "{$unsaved} siswa jawabannya belum sampai ke server lebih dari 2 menit" : 'semua jawaban sampai ke server',
            $unsaved > 0 ? 'warn' : 'good',
            $unsaved > 0 ? 'Buka Peta Kelas ujiannya (tab Keamanan) untuk melihat siapa, lalu periksa Wi-Fi siswa itu.' : null,
        );
    }

    /**
     * @return array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}
     */
    protected function serverErrors(): array
    {
        $open = ServerError::query()->whereNull('resolved_at')->count();
        $lastHour = ActivityLog::query()->where('event', ActivityLog::SERVER_ERROR)->where('created_at', '>=', now()->subHour())->count();

        return $this->check(
            'server_errors',
            'Galat server',
            $open > 0 ? "{$open} belum selesai" : 'Tidak ada',
            "{$lastHour} kejadian dalam 1 jam terakhir",
            $lastHour >= 10 ? 'bad' : ($open > 0 ? 'warn' : 'good'),
            $open > 0 ? 'Buka Galat Server untuk melihat apa yang terjadi dan siapa yang terkena.' : null,
        );
    }

    /**
     * @return array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}
     */
    protected function backup(): array
    {
        $latest = $this->backup->latest();
        $maxAgeHours = (int) config('maintenance.backup.max_age_hours');

        if ($latest === null) {
            return $this->check('backup', 'Backup database', 'Belum ada', null, 'bad', 'Jalankan php artisan db:backup di komputer server (lan:serve juga membuatnya otomatis setiap hari).');
        }

        $isStale = $latest['created_at']->lt(now()->subHours($maxAgeHours));

        return $this->check(
            'backup',
            'Backup database',
            (string) DisplayTime::ago($latest['created_at']),
            $latest['name'].' · '.$this->bytes($latest['size'], 1).' · '.count($this->backup->all()).' salinan',
            $isStale ? 'bad' : 'good',
            $isStale ? "Backup terakhir lebih dari {$maxAgeHours} jam: jalankan php artisan db:backup." : null,
        );
    }

    /**
     * @return array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}
     */
    protected function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if ($free === false || $total === false || $total <= 0) {
            return $this->check('disk', 'Ruang disk', 'Tidak diketahui', null, 'warn', null);
        }

        $percent = $free / $total * 100;
        $tone = $free < 1024 ** 3 || $percent < 5 ? 'bad' : ($free < 5 * 1024 ** 3 || $percent < 10 ? 'warn' : 'good');

        return $this->check(
            'disk',
            'Ruang disk',
            $this->bytes($free, 1).' kosong',
            'dari '.$this->bytes($total, 1).' ('.number_format($percent, 0).'% kosong)',
            $tone,
            $tone === 'good' ? null : 'Disk hampir penuh: database tidak dapat menyimpan jawaban. Hapus berkas besar yang tidak dipakai (video, unduhan) di komputer server.',
        );
    }

    /**
     * @return array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}
     */
    protected function migrations(): array
    {
        try {
            $pending = count($this->pendingMigrations->names());
        } catch (Throwable) {
            return $this->check('migrations', 'Struktur database', 'Tidak dapat diperiksa', null, 'warn', null);
        }

        return $this->check(
            'migrations',
            'Struktur database',
            $pending > 0 ? "{$pending} pembaruan tertunda" : 'Terbaru',
            null,
            $pending > 0 ? 'bad' : 'good',
            $pending > 0 ? 'Jalankan ulang php artisan lan:serve (memperbarui database otomatis), atau php artisan migrate --force.' : null,
        );
    }

    /**
     * @return array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}
     */
    protected function build(): array
    {
        $isMissing = ! is_file($this->assetBuild->manifestPath());
        $isOutdated = ! $isMissing && $this->assetBuild->isOutdated();

        return $this->check(
            'build',
            'Tampilan (build)',
            $isMissing ? 'Belum di-build' : ($isOutdated ? 'Lebih lama dari kode' : 'Sesuai kode terbaru'),
            $this->commit(),
            $isMissing ? 'bad' : ($isOutdated ? 'warn' : 'good'),
            $isMissing || $isOutdated ? 'Jalankan npm run build, lalu jalankan ulang server.' : null,
        );
    }

    /**
     * @return array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}
     */
    protected function environment(): array
    {
        $isDebug = (bool) config('app.debug');
        $isProduction = app()->environment('production');
        $workers = (int) (getenv('PHP_CLI_SERVER_WORKERS') ?: 0);

        return $this->check(
            'environment',
            'Mode aplikasi',
            $isProduction && ! $isDebug ? 'Produksi' : ($isDebug ? 'Debug menyala' : 'Bukan produksi'),
            'PHP '.PHP_VERSION.($workers > 0 ? " · {$workers} worker" : '').' · memori '.ini_get('memory_limit'),
            $isProduction && ! $isDebug ? 'good' : 'warn',
            $isProduction && ! $isDebug ? null : 'Untuk ujian, isi APP_ENV=production dan APP_DEBUG=false di .env (php artisan exam:optimize --production).',
        );
    }

    /**
     * @return array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}
     */
    protected function logs(): array
    {
        $size = 0;
        foreach (glob(storage_path('logs/*.log')) ?: [] as $file) {
            $size += (int) @filesize($file);
        }

        return $this->check(
            'logs',
            'Berkas log',
            $this->bytes($size, 1),
            'storage/logs',
            $size > 500 * 1024 ** 2 ? 'warn' : 'good',
            $size > 500 * 1024 ** 2 ? 'Log besar sekali: isi LOG_STACK=daily di .env agar log dibagi per hari dan yang lama terhapus sendiri.' : null,
        );
    }

    /**
     * The size of the database in bytes, when the database can tell.
     */
    protected function databaseSize(): ?int
    {
        return rescue(function (): ?int {
            $connection = DB::connection();

            return match ($connection->getDriverName()) {
                'pgsql' => (int) $connection->selectOne('select pg_database_size(current_database()) as size')->size,
                'mysql', 'mariadb' => (int) $connection->selectOne('select sum(data_length + index_length) as size from information_schema.tables where table_schema = database()')->size,
                'sqlite' => is_file((string) $connection->getDatabaseName()) ? (int) filesize((string) $connection->getDatabaseName()) : null,
                default => null,
            };
        }, null, report: false);
    }

    /**
     * The commit of the code this server runs, when it is a git checkout.
     */
    protected function commit(): ?string
    {
        $commit = trim((string) @shell_exec('git -C '.escapeshellarg(base_path()).' log -1 --format="%h %cd" --date=short 2>/dev/null'));

        return $commit !== '' ? "kode {$commit}" : null;
    }

    /**
     * A size as read by people: 1,5 GB, 820 MB, 12 KB.
     */
    protected function bytes(int|float $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return number_format($bytes, $unit === 0 ? 0 : $precision, ',', '.').' '.$units[$unit];
    }

    /**
     * @param  'good'|'warn'|'bad'  $tone
     * @return array{key: string, label: string, value: string, detail: string|null, tone: 'good'|'warn'|'bad', hint: string|null}
     */
    protected function check(string $key, string $label, string $value, ?string $detail, string $tone, ?string $hint): array
    {
        return compact('key', 'label', 'value', 'detail', 'tone', 'hint');
    }
}
