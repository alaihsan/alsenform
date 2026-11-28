<?php

namespace App\Console\Commands;

use App\Services\FileBackup;
use Illuminate\Console\Command;
use RuntimeException;

class BackupFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Salin berkas ujian (gambar, video, audio soal, berkas dan rekaman jawaban siswa) ke folder backup; hanya yang baru atau berubah';

    /**
     * Execute the console command.
     */
    public function handle(FileBackup $backup): int
    {
        try {
            $made = $backup->run();
        } catch (RuntimeException $exception) {
            report($exception);
            $this->error('Backup berkas gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('✓ Backup berkas tersimpan di '.$this->relative($made['path']).': '.$made['files'].' berkas ('.$this->size($made['bytes']).'), '
            .($made['copied'] > 0 ? $made['copied'].' baru atau berubah disalin ('.$this->size($made['copiedBytes']).').' : 'tidak ada yang baru.'));
        $this->line('   Untuk memulihkan: '.$backup->restoreHint());

        return self::SUCCESS;
    }

    protected function relative(string $path): string
    {
        return str_starts_with($path, base_path().DIRECTORY_SEPARATOR) ? substr($path, strlen(base_path()) + 1) : $path;
    }

    protected function size(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 KB';
        }

        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.').' MB' : number_format(max(1, $bytes / 1024), 0, ',', '.').' KB';
    }
}
