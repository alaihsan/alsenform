<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;
use RuntimeException;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup
                            {--if-older-than= : Hanya bila backup terakhir lebih lama dari sekian jam}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Buat salinan database (ujian, jawaban, nilai, akun) di storage/app/backups; salinan lama dihapus otomatis';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseBackup $backup): int
    {
        $hours = $this->option('if-older-than');

        if (is_numeric($hours) && ! $backup->isOlderThan((float) $hours)) {
            $latest = $backup->latest();
            $this->line("✓ Backup terbaru masih baru ({$latest['name']}), tidak perlu dibuat lagi.");

            return self::SUCCESS;
        }

        try {
            $made = $backup->run();
        } catch (RuntimeException $exception) {
            report($exception);
            $this->error('Backup database gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('✓ Backup database tersimpan: '.$this->relative($made['path']).' ('.$this->size($made['size']).').');
        $this->line('   Disimpan '.count($backup->all()).' salinan terbaru di '.$this->relative($backup->directory()).'. Untuk memulihkan: '.$backup->restoreHint($made['path']));

        return self::SUCCESS;
    }

    protected function relative(string $path): string
    {
        return str_starts_with($path, base_path().DIRECTORY_SEPARATOR) ? substr($path, strlen(base_path()) + 1) : $path;
    }

    protected function size(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.').' MB' : number_format(max(1, $bytes / 1024), 0, ',', '.').' KB';
    }
}
