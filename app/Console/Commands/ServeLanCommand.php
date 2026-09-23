<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeLanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lan:serve 
                            {--port=8000 : Port untuk menjalankan server}
                            {--host=0.0.0.0 : Host binding address}
                            {--build : Build asset Vite sebelum menjalankan server}
                            {--force : Hentikan proses yang sedang menggunakan port yang dipilih}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jalankan aplikasi Alsenform di jaringan lokal (Wi-Fi 5GHz, 2.4GHz, dan Kabel LAN)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $port = (int) $this->option('port');
        $host = (string) $this->option('host');

        if ($this->option('build') || ! file_exists(public_path('build/manifest.json'))) {
            $this->info('⚙️  Membuat bundle aset produksi (npm run build)...');
            $buildProcess = new Process(['npm', 'run', 'build'], base_path());
            $buildProcess->run(function ($type, $buffer): void {
                $this->output->write($buffer);
            });

            if (! $buildProcess->isSuccessful()) {
                $this->error('Gagal melakukan build aset.');

                return self::FAILURE;
            }
        }

        if ($this->isPortInUse($port, $host)) {
            $resolvedPort = $this->resolveBusyPort($port, $host);
            if ($resolvedPort === null) {
                return self::FAILURE;
            }
            $port = $resolvedPort;
        }

        $interfaces = $this->detectNetworkInterfaces();
        $hostname = $this->detectLocalHostName();

        $this->outputBanner($interfaces, $hostname, $port);

        // Run PHP development server on 0.0.0.0 with multi-worker support
        $command = [
            PHP_BINARY,
            'artisan',
            'serve',
            "--host={$host}",
            "--port={$port}",
            '--no-reload',
        ];

        $process = new Process($command, base_path(), null, null, null);
        $process->setTty(Process::isTtySupported());

        $cleanup = function () use ($process, $port): void {
            if ($process->isRunning()) {
                $process->stop(1, SIGINT);
            }
            $this->killPortProcesses($port);
        };

        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use ($cleanup): void {
                $cleanup();
                exit(0);
            });
            pcntl_signal(SIGTERM, function () use ($cleanup): void {
                $cleanup();
                exit(0);
            });
        }

        try {
            $process->run(function ($type, $buffer): void {
                $this->output->write($buffer);
            });
        } finally {
            $cleanup();
        }

        return $process->getExitCode() ?? self::SUCCESS;
    }

    /**
     * Check if a specific port is already in use.
     */
    public function isPortInUse(int $port, string $host = '127.0.0.1'): bool
    {
        $testHost = in_array($host, ['0.0.0.0', '::', ''], true) ? '127.0.0.1' : $host;
        $connection = @fsockopen($testHost, $port, $errno, $errstr, 0.2);

        if (is_resource($connection)) {
            fclose($connection);

            return true;
        }

        $output = @shell_exec("lsof -ti :{$port} 2>/dev/null");

        return ! empty(trim((string) $output));
    }

    /**
     * Retrieve process IDs listening on the given port.
     *
     * @return array<int, string>
     */
    public function getPortPids(int $port): array
    {
        $output = @shell_exec("lsof -ti :{$port} 2>/dev/null");
        if (! $output) {
            return [];
        }

        $pids = array_filter(array_map('trim', explode("\n", (string) $output)));

        return array_values(array_unique($pids));
    }

    /**
     * Terminate processes listening on the given port.
     */
    public function killPortProcesses(int $port): bool
    {
        $pids = $this->getPortPids($port);
        if (empty($pids)) {
            return true;
        }

        foreach ($pids as $pid) {
            if (is_numeric($pid)) {
                @shell_exec("kill -9 {$pid} 2>/dev/null");
            }
        }

        usleep(250 * 1000);

        return ! $this->isPortInUse($port);
    }

    /**
     * Find the next available port.
     */
    public function findAvailablePort(int $startPort, int $maxTries = 10): int
    {
        for ($i = 0; $i < $maxTries; $i++) {
            $candidate = $startPort + $i;
            if (! $this->isPortInUse($candidate)) {
                return $candidate;
            }
        }

        return $startPort;
    }

    /**
     * Resolve action when requested port is already busy.
     */
    protected function resolveBusyPort(int $port, string $host): ?int
    {
        $pids = $this->getPortPids($port);
        $pidStr = ! empty($pids) ? ' (PID: '.implode(', ', $pids).')' : '';

        if ($this->option('force')) {
            $this->warn("⚠️  Port {$port} sedang digunakan{$pidStr}. Menghentikan proses lama (--force)...");
            if ($this->killPortProcesses($port)) {
                $this->info("✓ Port {$port} berhasil dibebaskan.");

                return $port;
            }
            $this->error("Gagal menghentikan proses pada port {$port}.");

            return null;
        }

        if ($this->input->isInteractive()) {
            $this->newLine();
            $this->warn("⚠️  Port {$port} sedang digunakan oleh proses lain{$pidStr}.");

            if ($this->confirm("Apakah Anda ingin menghentikan proses lama tersebut dan melanjutkan di port {$port}?", true)) {
                $this->info("Menghentikan proses lama pada port {$port}...");
                if ($this->killPortProcesses($port)) {
                    $this->info("✓ Port {$port} berhasil dibebaskan.");

                    return $port;
                }
                $this->error("Gagal menghentikan proses pada port {$port}.");

                return null;
            }

            if ($this->confirm('Gunakan port alternatif berikutnya yang tersedia?', true)) {
                $availablePort = $this->findAvailablePort($port + 1);
                $this->info("Mengalihkan ke port {$availablePort}...");

                return $availablePort;
            }

            $this->error('Server dibatalkan.');

            return null;
        }

        if (! $this->hasExplicitOption('port')) {
            $availablePort = $this->findAvailablePort($port + 1);
            $this->warn("⚠️  Port {$port} sedang digunakan. Otomatis beralih ke port {$availablePort}.");

            return $availablePort;
        }

        $this->error("Port {$port} sedang digunakan{$pidStr}. Gunakan opsi --force untuk menghentikannya atau tentukan port lain dengan --port.");

        return null;
    }

    /**
     * Determine if an option was explicitly specified on the command line.
     */
    protected function hasExplicitOption(string $name): bool
    {
        return $this->input->hasParameterOption("--{$name}");
    }

    /**
     * Detect all active IPv4 network interfaces on the machine.
     *
     * @return array<string, string> Interface name => IP address
     */
    protected function detectNetworkInterfaces(): array
    {
        $results = [];

        // 1. Try PHP native net_get_interfaces()
        if (function_exists('net_get_interfaces')) {
            $allInterfaces = net_get_interfaces();
            if (is_array($allInterfaces)) {
                foreach ($allInterfaces as $name => $info) {
                    if (! empty($info['unicast'])) {
                        foreach ($info['unicast'] as $u) {
                            $addr = $u['address'] ?? '';
                            $family = $u['family'] ?? null;
                            $isIpv4 = defined('AF_INET') ? ($family === AF_INET) : ($family === 2);
                            if ($isIpv4 && $addr && $addr !== '127.0.0.1') {
                                $label = $this->resolveInterfaceLabel($name);
                                $results[$label] = $addr;
                            }
                        }
                    }
                }
            }
        }

        // 2. Fallback on macOS ipconfig if empty
        if (empty($results)) {
            foreach (['en0', 'en1', 'en2', 'en3', 'en4', 'en5'] as $iface) {
                $ip = trim((string) @shell_exec("ipconfig getifaddr {$iface} 2>/dev/null"));
                if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $label = $this->resolveInterfaceLabel($iface);
                    $results[$label] = $ip;
                }
            }
        }

        return $results;
    }

    /**
     * Translate macOS interface name (e.g. en0, en1) to human readable hardware port.
     */
    protected function resolveInterfaceLabel(string $iface): string
    {
        static $hardwareMap = null;

        if ($hardwareMap === null) {
            $hardwareMap = [];
            $output = @shell_exec('networksetup -listallhardwareports 2>/dev/null');
            if ($output) {
                preg_match_all('/Hardware Port:\s*([^\n]+)\s*\nDevice:\s*([^\n]+)/m', $output, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $hardwareMap[trim($match[2])] = trim($match[1]);
                }
            }
        }

        $portName = $hardwareMap[$iface] ?? null;

        return $portName ? "{$portName} ({$iface})" : $iface;
    }

    /**
     * Detect Bonjour/mDNS local hostname.
     */
    protected function detectLocalHostName(): ?string
    {
        $name = trim((string) @shell_exec('scutil --get LocalHostName 2>/dev/null'));
        if (! $name) {
            $name = trim((string) @gethostname());
        }

        return $name ? "{$name}.local" : null;
    }

    /**
     * Print the informative console banner.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function outputBanner(array $interfaces, ?string $hostname, int $port): void
    {
        $this->newLine();
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold>   🚀 ALSENFORM - SIAP DIGUNAKAN DI JARINGAN LOKAL (LAN / WI-FI)        </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();

        $this->output->writeln(' <fg=green;options=bold>✓ Jaringan & Frekuensi yang Didukung:</>');
        $this->output->writeln('   📶 <options=bold>Wi-Fi 5 GHz</>     : Sangat cepat & latensi rendah (cocok untuk ujian serentak)');
        $this->output->writeln('   📶 <options=bold>Wi-Fi 2.4 GHz</>   : Jangkauan lebih luas untuk perangkat di kelas/ruang jauh');
        $this->output->writeln('   🔌 <options=bold>Kabel LAN (RJ45)</>: Koneksi paling stabil untuk komputer lab sekolah');
        $this->newLine();

        $this->output->writeln(' <fg=yellow;options=bold>🌐 URL Akses untuk Siswa, Guru & Pengawas:</>');
        if (! empty($interfaces)) {
            foreach ($interfaces as $label => $ip) {
                $this->output->writeln("   👉 <fg=cyan;options=bold>http://{$ip}:{$port}</> <fg=gray>({$label})</>");
            }
        } else {
            $this->output->writeln("   👉 <fg=cyan;options=bold>http://0.0.0.0:{$port}</>");
        }

        if ($hostname) {
            $this->output->writeln("   👉 <fg=magenta;options=bold>http://{$hostname}:{$port}</> <fg=gray>(Perangkat Apple / mDNS Bonjour)</>");
        }

        $this->output->writeln("   👉 <fg=white>http://localhost:{$port}</> <fg=gray>(Hanya di komputer ini)</>");

        $this->newLine();
        $this->output->writeln(' <fg=white;options=bold>📋 Petunjuk Pemakaian di Kelas / Lab:</>');
        $this->output->writeln('   1. Pastikan perangkat siswa terhubung ke Wi-Fi / kabel router yang sama.');
        $this->output->writeln('   2. Siswa cukup membuka browser (Chrome/Safari) dan mengetik URL di atas.');
        $this->output->writeln('   3. Jika tidak bisa diakses, pastikan fitur "AP Isolation" di router telah dinonaktifkan.');
        $this->newLine();
        $this->output->writeln(' <fg=gray>Tekan CTRL+C untuk menghentikan server.</>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();
    }
}
