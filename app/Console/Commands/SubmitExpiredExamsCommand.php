<?php

namespace App\Console\Commands;

use App\Actions\SubmitExpiredAttempts;
use Illuminate\Console\Command;

class SubmitExpiredExamsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:submit-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim otomatis jawaban ujian yang waktunya sudah habis tetapi belum dikirim (halaman ditutup, offline, atau baterai habis)';

    /**
     * Execute the console command.
     */
    public function handle(SubmitExpiredAttempts $submitExpiredAttempts): int
    {
        $submitted = $submitExpiredAttempts->handle();

        $this->info($submitted > 0
            ? "✓ {$submitted} ujian yang waktunya habis dikirim otomatis."
            : 'Tidak ada ujian yang waktunya habis dan belum dikirim.');

        return self::SUCCESS;
    }
}
