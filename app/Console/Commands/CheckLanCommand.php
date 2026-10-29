<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckLanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lan:check {--port='.ServeLanCommand::DEFAULT_PORT.' : Port server Falcon Forms}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa mengapa alamat intranet (http://falcon.intranet:2026) belum berfungsi, beserta cara memperbaikinya';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        return $this->call('lan:serve', [
            '--check' => true,
            '--port' => $this->option('port'),
        ]);
    }
}
