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
                            {--workers=24 : Jumlah concurrent worker process untuk melayani hingga ratusan siswa}
                            {--optimize : Jalankan optimasi cache sebelum server mulai}
                            {--build : Build asset Vite sebelum menjalankan server}
                            {--force : Hentikan proses yang sedang menggunakan port yang dipilih}
                            {--d|daemon : Jalankan server di latar belakang (background mode)}
                            {--stop : Hentikan server yang sedang berjalan di latar belakang}
                            {--status : Periksa status server yang sedang berjalan di latar belakang}
                            {--no-caffeinate : Jangan aktifkan proteksi anti-sleep macOS}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jalankan aplikasi Alsenform di jaringan lokal (Wi-Fi 5GHz, 2.4GHz, dan Kabel LAN) dengan proteksi Anti-Sleep';

    /**
     * How often (in seconds) the server checks whether its IP address changed.
     */
    protected float $networkCheckIntervalSeconds = 5.0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('stop')) {
            return $this->handleStop();
        }

        if ($this->option('status')) {
            return $this->handleStatus();
        }

        $port = (int) $this->option('port');
        $host = (string) $this->option('host');
        $workers = max(4, (int) $this->option('workers'));

        if ($port < 1 || $port > 65535) {
            $this->error('Port harus bernilai antara 1 dan 65535.');

            return self::INVALID;
        }

        if (! $this->isValidHost($host)) {
            $this->error('Host harus berupa alamat IPv4 yang valid, 0.0.0.0, atau localhost.');

            return self::INVALID;
        }

        if ($this->option('optimize')) {
            $this->call('exam:optimize');
        }

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

        if ($this->option('daemon')) {
            return $this->handleDaemon($port, $host, $workers, $interfaces, $hostname);
        }

        $useCaffeinate = ! $this->option('no-caffeinate') && file_exists('/usr/bin/caffeinate');

        $this->outputBanner($interfaces, $hostname, $port, $workers, $useCaffeinate);

        // Run PHP development server on 0.0.0.0 with multi-worker support and Anti-Sleep
        $serverScript = base_path('server.php');
        $command = [
            PHP_BINARY,
            '-S',
            "{$host}:{$port}",
            $serverScript,
        ];

        if ($useCaffeinate) {
            array_unshift($command, '/usr/bin/caffeinate', '-dimsu');
        }

        $env = array_merge($_ENV, $_SERVER, [
            'PHP_CLI_SERVER_WORKERS' => (string) $workers,
        ]);
        $applicationUrl = $this->applicationUrl($interfaces, $host, $port);
        if ($applicationUrl) {
            $env['APP_URL'] = $applicationUrl;
        }

        $process = new Process($command, base_path(), $env, null, null);
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
            $process->start(function ($type, $buffer): void {
                $this->output->write($buffer);
            });

            $this->watchNetworkChanges($process, $interfaces, $host, $port);
        } finally {
            $cleanup();
        }

        return $process->getExitCode() ?? self::SUCCESS;
    }

    /**
     * Keep the server in the foreground and announce the new student URLs whenever
     * the IP address of this machine changes (DHCP renewal, switching Wi-Fi, cable).
     *
     * @param  array<string, string>  $interfaces
     */
    protected function watchNetworkChanges(Process $process, array $interfaces, string $host, int $port): void
    {
        $knownAddresses = $this->addressList($interfaces);
        $lastCheckedAt = microtime(true);

        while ($process->isRunning()) {
            usleep((int) (min(0.5, $this->networkCheckIntervalSeconds) * 1_000_000));

            if (microtime(true) - $lastCheckedAt < $this->networkCheckIntervalSeconds) {
                continue;
            }
            $lastCheckedAt = microtime(true);

            $currentInterfaces = $this->detectNetworkInterfaces();
            $currentAddresses = $this->addressList($currentInterfaces);

            if ($currentAddresses !== $knownAddresses) {
                $knownAddresses = $currentAddresses;
                $this->announceNetworkChange($currentInterfaces, $host, $port);
            }
        }
    }

    /**
     * Sorted list of IP addresses, used to detect a network change.
     *
     * @param  array<string, string>  $interfaces
     * @return list<string>
     */
    protected function addressList(array $interfaces): array
    {
        $addresses = array_values(array_unique($interfaces));
        sort($addresses);

        return $addresses;
    }

    /**
     * Tell the operator which URL students must use after the IP address changed.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function announceNetworkChange(array $interfaces, string $host, int $port): void
    {
        $this->newLine();
        $this->output->writeln('<bg=yellow;fg=black;options=bold> ⚠️  ALAMAT IP SERVER BERUBAH ('.date('H:i:s').') </>');

        if (! in_array($host, ['0.0.0.0', 'localhost'], true) && ! in_array($host, $interfaces, true)) {
            $this->output->writeln("   <fg=red;options=bold>Server terikat ke {$host} yang sudah tidak tersedia.</> Jalankan ulang dengan <fg=yellow>php artisan lan:serve --host=0.0.0.0</>");
        } else {
            $this->output->writeln('   Server tetap berjalan. Jawaban siswa aman (tersimpan di perangkat dan di server).');
        }

        if (empty($interfaces)) {
            $this->output->writeln('   <fg=red>Tidak ada jaringan aktif. Periksa Wi-Fi / kabel LAN server.</>');
        } else {
            $this->output->writeln('   Bagikan URL baru ini ke siswa (lalu login ulang, jawaban akan dipulihkan otomatis):');
            foreach ($interfaces as $label => $ip) {
                $this->output->writeln("      👉 <fg=cyan;options=bold>http://{$ip}:{$port}</> <fg=gray>({$label})</>");
            }
        }

        $this->output->writeln('   💡 Agar IP tidak berubah lagi: atur DHCP Reservation (IP tetap) untuk server ini di router.');
        $this->newLine();
    }

    /**
     * Start the server as a background daemon process.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function handleDaemon(int $port, string $host, int $workers, array $interfaces, ?string $hostname): int
    {
        $logPath = storage_path('logs/lan_server.log');
        $infoFile = storage_path('framework/lan_server.json');

        if (file_exists($infoFile)) {
            $existingServer = json_decode((string) file_get_contents($infoFile), true);
            $existingPort = (int) ($existingServer['port'] ?? 0);

            if ($existingPort !== $port && $existingPort > 0 && $this->isPortInUse($existingPort)) {
                $this->error("Server LAN sudah berjalan pada port {$existingPort}. Hentikan terlebih dahulu sebelum menjalankan server baru.");

                return self::FAILURE;
            }
        }

        if (! file_exists(dirname($logPath))) {
            @mkdir(dirname($logPath), 0755, true);
        }
        if (! file_exists(dirname($infoFile))) {
            @mkdir(dirname($infoFile), 0755, true);
        }

        $phpBinary = escapeshellarg(PHP_BINARY);
        $serverScript = escapeshellarg(base_path('server.php'));
        $useCaffeinate = ! $this->option('no-caffeinate') && file_exists('/usr/bin/caffeinate');

        if ($useCaffeinate) {
            $execCmd = "/usr/bin/caffeinate -dimsu {$phpBinary} -S {$host}:{$port} {$serverScript}";
        } else {
            $execCmd = "{$phpBinary} -S {$host}:{$port} {$serverScript}";
        }

        $applicationUrl = $this->applicationUrl($interfaces, $host, $port);
        $environment = 'PHP_CLI_SERVER_WORKERS='.$workers;
        if ($applicationUrl) {
            $environment .= ' APP_URL='.escapeshellarg($applicationUrl);
        }

        $fullCmd = sprintf(
            '%s nohup %s < /dev/null >> %s 2>&1 & echo $!',
            $environment,
            $execCmd,
            escapeshellarg($logPath)
        );

        $pid = trim((string) @shell_exec($fullCmd));

        if (! is_numeric($pid) || (int) $pid <= 0) {
            $this->error('Gagal menjalankan server di latar belakang.');

            return self::FAILURE;
        }

        $data = [
            'pid' => (int) $pid,
            'port' => $port,
            'host' => $host,
            'workers' => $workers,
            'started_at' => time(),
            'caffeinate' => $useCaffeinate,
        ];
        // Allow child processes to bind to port
        usleep(800 * 1000);

        $portPids = $this->getPortPids($port);
        $isAlive = ! empty($portPids) || (! empty(trim((string) @shell_exec("ps -p {$pid} -o pid= 2>/dev/null"))));
        if (! $isAlive) {
            $this->error("Server gagal berjalan. Periksa berkas log: {$logPath}");

            return self::FAILURE;
        }

        $activePid = ! empty($portPids) ? (int) $portPids[0] : (int) $pid;
        $data['pid'] = $activePid;
        file_put_contents($infoFile, json_encode($data, JSON_PRETTY_PRINT));

        $this->outputDaemonBanner($interfaces, $hostname, $port, $workers, $activePid, $useCaffeinate, $logPath);

        return self::SUCCESS;
    }

    /**
     * Stop the background daemon server.
     */
    protected function handleStop(): int
    {
        $infoFile = storage_path('framework/lan_server.json');
        $port = (int) $this->option('port');

        if (! file_exists($infoFile)) {
            if ($this->isPortInUse($port)) {
                $this->warn("⚠️  Port {$port} sedang digunakan, tetapi tidak memiliki metadata Alsenform.");
                $this->line('   Demi keamanan, tidak ada proses yang dihentikan. Periksa proses tersebut secara manual.');

                return self::FAILURE;
            } else {
                $this->info('ℹ️  Tidak ada server latar belakang yang sedang aktif.');
            }

            return self::SUCCESS;
        }

        $data = json_decode((string) file_get_contents($infoFile), true);
        $pid = (int) ($data['pid'] ?? 0);
        $serverPort = (int) ($data['port'] ?? $port);

        if ($pid > 0) {
            @shell_exec("kill -TERM {$pid} 2>/dev/null");
            usleep(200 * 1000);
            @shell_exec("kill -9 {$pid} 2>/dev/null");
        }

        $this->killPortProcesses($serverPort);
        @unlink($infoFile);

        $this->newLine();
        $this->output->writeln('<bg=green;fg=white;options=bold> ========================================================================= </>');
        $this->output->writeln('<bg=green;fg=white;options=bold>   🛑 SERVER ALSENFORM LATAR BELAKANG BERHASIL DIHENTIKAN                 </>');
        $this->output->writeln('<bg=green;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();
        $this->info("✓ Server pada port {$serverPort} telah dihentikan.");
        $this->info('✓ Proteksi Anti-Sleep dilepas. Pengaturan daya Mac Mini kembali ke mode normal.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Check status of the background daemon server.
     */
    protected function handleStatus(): int
    {
        $infoFile = storage_path('framework/lan_server.json');

        if (! file_exists($infoFile)) {
            $port = (int) $this->option('port');
            if ($this->isPortInUse($port)) {
                $this->warn("⚠️  Server berjalan pada port {$port} (tanpa rekaman metadata background).");
            } else {
                $this->info('ℹ️  Server Alsenform sedang TIDAK berjalan di latar belakang.');
                $this->line('   Jalankan dengan: <fg=yellow>php artisan lan:serve --daemon</>');
            }

            return self::SUCCESS;
        }

        $data = json_decode((string) file_get_contents($infoFile), true);
        $pid = (int) ($data['pid'] ?? 0);
        $port = (int) ($data['port'] ?? 8000);
        $portPids = $this->getPortPids($port);
        $isRunning = ! empty($portPids) || ($pid > 0 && ! empty(trim((string) @shell_exec("ps -p {$pid} -o pid= 2>/dev/null"))));

        if (! $isRunning) {
            @unlink($infoFile);
            $this->warn("⚠️  Proses server (PID {$pid}) sudah tidak aktif. File status dibersihkan.");
            $this->line('   Jalankan kembali dengan: <fg=yellow>php artisan lan:serve --daemon</>');

            return self::SUCCESS;
        }

        $uptimeSeconds = time() - (int) ($data['started_at'] ?? time());
        $uptimeFormatted = sprintf('%02dh %02dm %02ds', ($uptimeSeconds / 3600), ($uptimeSeconds / 60 % 60), $uptimeSeconds % 60);

        $this->newLine();
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold>   🟢 STATUS SERVER ALSENFORM (BACKGROUND DAEMON)                         </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();
        $this->output->writeln('   • Status           : <fg=green;options=bold>AKTIF / BERJALAN DI LATAR BELAKANG</>');
        $this->output->writeln("   • Process ID (PID) : <fg=cyan;options=bold>{$pid}</>");
        $this->output->writeln("   • Port Jaringan    : <fg=cyan;options=bold>{$port}</>");
        $this->output->writeln('   • Multi-Workers    : <fg=cyan>'.($data['workers'] ?? 24).' concurrent processes</>');
        $this->output->writeln("   • Uptime (Aktif)   : <fg=yellow;options=bold>{$uptimeFormatted}</>");
        $this->output->writeln('   • Anti-Sleep (Mac) : <fg=green;options=bold>AKTIF (Caffeinate - Mac Mini M2 Tidak Akan Sleep/Tidur)</>');
        $this->output->writeln('   • Berkas Log       : <fg=gray>storage/logs/lan_server.log</>');
        $this->newLine();

        $interfaces = $this->detectNetworkInterfaces();
        $this->output->writeln('   🌐 <options=bold>Akses URL Siswa:</>');
        foreach ($interfaces as $label => $ip) {
            $this->output->writeln("      👉 <fg=cyan;options=bold>http://{$ip}:{$port}</> <fg=gray>({$label})</>");
        }
        $this->newLine();
        $this->output->writeln('   Perintah manajemen:');
        $this->output->writeln('   • Hentikan server  : <fg=red>php artisan lan:stop</> atau <fg=red>php artisan lan:serve --stop</>');
        $this->output->writeln('   • Pantau log live  : <fg=yellow>tail -f storage/logs/lan_server.log</>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();

        return self::SUCCESS;
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
     * Verify the host can be used safely in the daemon command.
     */
    protected function isValidHost(string $host): bool
    {
        return in_array($host, ['0.0.0.0', 'localhost'], true)
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Resolve the LAN URL used for assets generated by the application.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function applicationUrl(array $interfaces, string $host, int $port): ?string
    {
        $address = $host !== '0.0.0.0' && $host !== 'localhost' ? $host : reset($interfaces);

        return $address ? "http://{$address}:{$port}" : null;
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

            $choices = [
                'kill' => "Hentikan paksa proses yang menggunakan port {$port} lalu gunakan port ini",
                'next' => 'Cari dan gunakan port kosong berikutnya secara otomatis',
                'cancel' => 'Batalkan',
            ];

            $choice = $this->choice('Pilih tindakan yang ingin dilakukan:', $choices, 'kill');

            if ($choice === 'kill') {
                $this->info("Menghentikan proses pada port {$port}...");
                if ($this->killPortProcesses($port)) {
                    $this->info("✓ Port {$port} berhasil dibebaskan.");

                    return $port;
                }
                $this->error("Gagal membebaskan port {$port}.");
            } elseif ($choice === 'next') {
                $newPort = $this->findAvailablePort($port + 1);
                $this->info("✓ Menggunakan port baru yang tersedia: {$newPort}");

                return $newPort;
            }

            return null;
        }

        $newPort = $this->findAvailablePort($port + 1);
        $this->warn("⚠️  Port {$port} sedang sibuk. Otomatis beralih ke port {$newPort}.");

        return $newPort;
    }

    /**
     * Determine if an option was explicitly provided by the user.
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
     * Print banner when running as background daemon.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function outputDaemonBanner(array $interfaces, ?string $hostname, int $port, int $workers, int $pid, bool $useCaffeinate, string $logPath): void
    {
        $this->newLine();
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold>   🚀 ALSENFORM BERJALAN DI LATAR BELAKANG (BACKGROUND DAEMON)           </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();

        $this->output->writeln(" <fg=green;options=bold>✓ Status Server:</> <fg=green;options=bold>AKTIF</> (PID: <fg=cyan;options=bold>{$pid}</>)");
        $this->output->writeln(" <fg=green;options=bold>✓ Multi-Workers:</> <fg=cyan>{$workers} concurrent processes</> (mencegah antrean)");
        if ($useCaffeinate) {
            $this->output->writeln(' <fg=green;options=bold>✓ Anti-Sleep Mac:</> <fg=cyan;options=bold>AKTIF (Caffeinate)</> - Mac Mini tidak akan tidur selama server aktif');
        }
        $this->output->writeln(" <fg=green;options=bold>✓ Berkas Log:</> <fg=gray>{$logPath}</>");
        $this->newLine();

        $this->output->writeln(' <fg=yellow;options=bold>🌐 URL Akses untuk Siswa, Guru & Pengawas:</>');
        if (! empty($interfaces)) {
            foreach ($interfaces as $label => $ip) {
                $this->output->writeln("   👉 <fg=cyan;options=bold>http://{$ip}:{$port}</> <fg=gray>({$label})</>");
            }
        } else {
            $this->output->writeln("   👉 <fg=cyan;options=bold>http://0.0.0.0:{$port}</>");
        }

        $this->output->writeln('   👉 <fg=green;options=bold>http://alsenform.test</> <fg=gray>(Jika router memiliki DNS / via Nginx Herd Port 80)</>');
        if ($hostname) {
            $this->output->writeln("   👉 <fg=magenta;options=bold>http://{$hostname}:{$port}</> <fg=gray>(Perangkat Apple / mDNS Bonjour)</>");
        }
        $this->newLine();

        $this->output->writeln(' <fg=white;options=bold>🕹️ Perintah Manajemen Server:</>');
        $this->output->writeln('   • Cek status server : <fg=yellow>php artisan lan:status</> atau <fg=yellow>php artisan lan:serve --status</>');
        $this->output->writeln('   • Hentikan server   : <fg=red>php artisan lan:stop</> atau <fg=red>php artisan lan:serve --stop</>');
        $this->output->writeln('   • Pantau log live   : <fg=cyan>tail -f storage/logs/lan_server.log</>');
        $this->newLine();
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();
    }

    /**
     * Print the informative console banner.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function outputBanner(array $interfaces, ?string $hostname, int $port, int $workers = 24, bool $useCaffeinate = true): void
    {
        $this->newLine();
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold>   🚀 ALSENFORM - SIAP DIGUNAKAN DI JARINGAN LOKAL (LAN / WI-FI)        </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();

        $this->output->writeln(' <fg=green;options=bold>✓ Performa & Kapasitas Jaringan (200+ Peserta):</>');
        $this->output->writeln("   ⚡ <options=bold>Multi-Workers</>  : <fg=cyan>{$workers} concurrent processes</> (anti antrean request)");
        $this->output->writeln('   ⚡ <options=bold>Kompresi Gzip</>  : <fg=cyan>Aktif</> (menghemat bandwidth intranet ~80%)');
        $this->output->writeln('   ⚡ <options=bold>Cache Aset VITE</>: <fg=cyan>Aktif (Immutable)</> (0-byte reload di browser siswa)');
        $this->output->writeln('   ⚡ <options=bold>Kompresi Media</> : <fg=cyan>WebP Auto-Scale</> (gambar otomatis < 300KB)');
        if ($useCaffeinate) {
            $this->output->writeln('   ⚡ <options=bold>Anti-Sleep (Mac)</>: <fg=cyan;options=bold>AKTIF (Caffeinate)</> (Mac Mini tidak akan tidur selama server aktif)');
        }
        $this->newLine();

        $this->output->writeln(' <fg=green;options=bold>✓ Jaringan & Frekuensi yang Didukung:</>');
        $this->output->writeln('   📶 <options=bold>Wi-Fi 5 GHz</>     : Sangat cepat & latensi rendah (sangat direkomendasikan)');
        $this->output->writeln('   📶 <options=bold>Wi-Fi 2.4 GHz</>   : Jangkauan lebih luas untuk perangkat di kelas/ruang jauh');
        $this->output->writeln('   🔌 <options=bold>Kabel LAN (RJ45)</>: Koneksi paling stabil untuk komputer server/lab');
        $this->newLine();

        $this->output->writeln(' <fg=yellow;options=bold>🌐 URL Akses untuk Siswa, Guru & Pengawas:</>');
        if (! empty($interfaces)) {
            foreach ($interfaces as $label => $ip) {
                $this->output->writeln("   👉 <fg=cyan;options=bold>http://{$ip}:{$port}</> <fg=gray>({$label})</>");
            }
        } else {
            $this->output->writeln("   👉 <fg=cyan;options=bold>http://0.0.0.0:{$port}</>");
        }

        $this->output->writeln('   👉 <fg=green;options=bold>http://alsenform.test</> <fg=gray>(Jika router memiliki DNS / via Nginx Herd Port 80)</>');

        if ($hostname) {
            $this->output->writeln("   👉 <fg=magenta;options=bold>http://{$hostname}:{$port}</> <fg=gray>(Perangkat Apple / mDNS Bonjour)</>");
        }

        $this->output->writeln("   👉 <fg=white>http://localhost:{$port}</> <fg=gray>(Hanya di komputer ini)</>");

        $this->newLine();
        $this->output->writeln(' <fg=white;options=bold>📋 Petunjuk Pemakaian di Kelas / Lab:</>');
        $this->output->writeln('   1. Pastikan perangkat siswa terhubung ke Wi-Fi / kabel router yang sama.');
        $this->output->writeln('   2. Siswa cukup membuka browser (Chrome/Safari) dan mengetik URL di atas.');
        $this->output->writeln('   3. Jika tidak bisa diakses, pastikan fitur "AP Isolation" di router telah dinonaktifkan.');
        $this->output->writeln('   💡 <options=bold>Jalankan di latar belakang:</> <fg=yellow>php artisan lan:serve --daemon</>');
        $this->newLine();
        $this->output->writeln(' <fg=gray>Tekan CTRL+C untuk menghentikan server.</>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();
    }
}
