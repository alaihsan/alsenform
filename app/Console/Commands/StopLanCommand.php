<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StopLanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lan:stop {--port=8000 : Port server Alsenform}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hentikan server Alsenform yang sedang berjalan di latar belakang';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        return $this->call('lan:serve', [
            '--stop' => true,
            '--port' => $this->option('port'),
        ]);
    }
}
