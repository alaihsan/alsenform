<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StatusLanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lan:status {--port=8000 : Port server Alsenform}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa status server Alsenform yang sedang berjalan di latar belakang';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        return $this->call('lan:serve', [
            '--status' => true,
            '--port' => $this->option('port'),
        ]);
    }
}
