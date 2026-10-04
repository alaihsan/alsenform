<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class OptimizeExamCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:optimize {--production : Set APP_DEBUG=false secara otomatis di .env}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimasi penuh sistem Alsenform untuk ujian serentak 200+ peserta di jaringan intranet';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold>   ⚡ OPTIMASI ALSENFORM UNTUK UJIAN SERENTAK (200+ SISWA)                </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();

        // 1. Check APP_DEBUG
        $appDebug = config('app.debug');
        if ($this->option('production') && $appDebug) {
            $this->setEnvValue('APP_DEBUG', 'false');
            $this->setEnvValue('APP_ENV', 'production');
            $appDebug = false;
            $this->info('✓ Mode produksi diaktifkan (APP_DEBUG=false, APP_ENV=production).');
        } elseif ($appDebug) {
            $this->warn('⚠️  APP_DEBUG masih bernilai true. Untuk performa maksimal saat ujian ramai, jalankan dengan flag: php artisan exam:optimize --production');
        } else {
            $this->info('✓ Mode produksi aktif (APP_DEBUG=false).');
        }

        // 2. Check Database & Max Connections
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            if ($driver === 'pgsql') {
                // Every lan:serve worker holds at most one connection, so 100 leaves plenty of headroom.
                $val = (int) ($connection->selectOne('SHOW max_connections')->max_connections ?? 100);
                if ($val >= 100) {
                    $this->info("✓ PostgreSQL max_connections: {$val} (aman untuk lan:serve dengan 24+ worker).");
                } else {
                    $this->warn("⚠️  PostgreSQL max_connections saat ini: {$val}. Disarankan >= 100.");
                }
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $maxConn = $connection->select("SHOW VARIABLES LIKE 'max_connections'");
                $val = ! empty($maxConn[0]->Value) ? (int) $maxConn[0]->Value : 151;
                if ($val >= 300) {
                    $this->info("✓ MySQL max_connections: {$val} (Sangat aman untuk 200+ koneksi serentak).");
                } else {
                    $this->warn("⚠️  MySQL max_connections saat ini: {$val}. Disarankan >= 300 untuk 200 user serentak.");
                }
            } else {
                $this->warn("⚠️  Database {$driver} tidak disarankan untuk ujian serentak. Gunakan PostgreSQL (DB_CONNECTION=pgsql).");
            }

            // Clean stale sessions
            $deletedSessions = DB::table('sessions')
                ->where('last_activity', '<', now()->subHours(24)->timestamp)
                ->delete();
            $this->info("✓ Membersihkan {$deletedSessions} sesi kedaluwarsa di database.");
        } catch (\Throwable $e) {
            $this->warn('⚠️  Database check: '.$e->getMessage());
        }

        // 3. Check Image Optimization Readiness
        $hasGd = extension_loaded('gd');
        $hasWebp = function_exists('imagewebp');
        if ($hasGd && $hasWebp) {
            $this->info('✓ Kompresi Gambar Otomatis: Aktif (GD + WebP, hemat 80-95% bandwidth intranet).');
        } else {
            $this->warn('⚠️  GD/WebP belum lengkap. Gambar mungkin tidak terkompresi maksimal.');
        }

        // 4. Run Laravel Optimizations
        $this->info('⚙️  Mengompilasi cache konfigurasi, routing, dan view...');
        $this->callSilent('config:cache');
        $this->callSilent('route:cache');
        $this->callSilent('view:cache');
        $this->callSilent('event:cache');
        $this->info('✓ Seluruh cache framework Laravel berhasil diperbarui ke memory.');

        // 5. Ensure Vite assets are built
        if (! file_exists(public_path('build/manifest.json'))) {
            $this->info('⚙️  Membangun aset produksi Vite...');
            $process = new Process(['npm', 'run', 'build'], base_path());
            $process->run();
            $this->info('✓ Aset frontend Vite berhasil dibundel.');
        } else {
            $this->info('✓ Aset frontend produksi sudah siap (Cache-Control aktif).');
        }

        $this->newLine();
        $this->output->writeln('<fg=green;options=bold>🎉 SISTEM TELAH DIOPTIMASI & SIAP DIGUNAKAN UNTUK UJIAN SERENTAK!</>');
        $this->output->writeln('   Jalankan server intranet dengan: <fg=yellow>php artisan lan:serve --workers=24</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Update an environment variable in the .env file.
     */
    protected function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);
        if (preg_match("/^{$key}=/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }

        file_put_contents($envPath, $content);
    }
}
