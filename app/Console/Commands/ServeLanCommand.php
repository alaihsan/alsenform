<?php

namespace App\Console\Commands;

use App\Actions\SubmitExpiredAttempts;
use App\Support\DnsLookup;
use App\Support\PendingMigrations;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;
use Throwable;

class ServeLanCommand extends Command
{
    /**
     * The port of the intranet address, http://falcon.lan:2026.
     */
    public const DEFAULT_PORT = 2026;

    /**
     * The port of the address used before, such as http://alsenform.local:8000.
     */
    public const LEGACY_PORT = 8000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lan:serve 
                            {--port='.self::DEFAULT_PORT.' : Port untuk menjalankan server}
                            {--host=0.0.0.0 : Host binding address}
                            {--workers=24 : Jumlah concurrent worker process untuk melayani hingga ratusan siswa}
                            {--optimize : Jalankan optimasi cache sebelum server mulai}
                            {--build : Build asset Vite sebelum menjalankan server}
                            {--force : Hentikan proses yang sedang menggunakan port yang dipilih}
                            {--d|daemon : Jalankan server di latar belakang (background mode)}
                            {--stop : Hentikan server yang sedang berjalan di latar belakang}
                            {--status : Periksa status server yang sedang berjalan di latar belakang}
                            {--check : Periksa semua yang dibutuhkan alamat intranet (http://falcon.lan:2026) beserta cara memperbaikinya}
                            {--no-caffeinate : Jangan aktifkan proteksi anti-sleep macOS}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jalankan aplikasi Falcon Forms di jaringan lokal (Wi-Fi 5GHz, 2.4GHz, dan Kabel LAN) dengan proteksi Anti-Sleep';

    /**
     * How often (in seconds) the server checks whether its IP address changed.
     */
    protected float $networkCheckIntervalSeconds = 5.0;

    /**
     * How often (in seconds) the server hands in the exams whose time ran out.
     */
    protected float $autoSubmitIntervalSeconds = 60.0;

    /**
     * What the network's DNS answered for each name, looked up once per network.
     *
     * @var array<string, string|null>
     */
    protected array $resolvedHosts = [];

    /**
     * The problems found so far by lan:check.
     */
    protected int $checkProblems = 0;

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

        if ($this->option('check')) {
            return $this->handleCheck();
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

        if (! $this->databaseIsReachable()) {
            return self::FAILURE;
        }

        $this->syncWithCurrentCode();

        if ($this->option('optimize')) {
            $this->call('exam:optimize');
        }

        $hasBuild = file_exists($this->manifestPath());
        $isBuildOutdated = $hasBuild && $this->assetsAreOutdated();

        if ($this->option('build') || ! $hasBuild || $isBuildOutdated) {
            $this->info($isBuildOutdated
                ? '⚙️  Kode tampilan lebih baru daripada bundle aset (setelah git pull/merge): membuat ulang (npm run build)...'
                : '⚙️  Membuat bundle aset produksi (npm run build)...');
            $buildProcess = new Process(['npm', 'run', 'build'], base_path());
            $buildProcess->setTimeout(600);
            $buildProcess->run(function ($type, $buffer): void {
                $this->output->write($buffer);
            });

            if (! $buildProcess->isSuccessful()) {
                $this->error('Gagal melakukan build aset.');

                // Pages still work with the previous bundle, only without the newest changes.
                if (! $hasBuild) {
                    return self::FAILURE;
                }

                $this->warn('Server tetap berjalan dengan tampilan lama: tombol atau fitur baru belum muncul sampai "npm run build" berhasil.');
            }
        }

        $this->retireLegacyServer($port);
        $this->removeStaleViteHotFile();

        if ($this->isPortInUse($port, $host)) {
            $resolvedPort = $this->resolveBusyPort($port, $host);
            if ($resolvedPort === null) {
                return self::FAILURE;
            }
            $port = $resolvedPort;
        }

        $interfaces = $this->detectNetworkInterfaces();

        if ($this->option('daemon')) {
            return $this->handleDaemon($port, $host, $workers, $interfaces);
        }

        $useCaffeinate = ! $this->option('no-caffeinate') && file_exists('/usr/bin/caffeinate');

        $this->outputBanner($interfaces, $port, $workers, $useCaffeinate);

        // Run PHP development server on 0.0.0.0 with multi-worker support and Anti-Sleep
        $serverScript = base_path('server.php');
        $command = [
            PHP_BINARY,
            ...$this->phpRuntimeOptions(),
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
     * Meanwhile, exams whose time ran out while the page was closed are handed in.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function watchNetworkChanges(Process $process, array $interfaces, string $host, int $port): void
    {
        $knownAddresses = $this->addressList($interfaces);
        $lastCheckedAt = microtime(true);
        // Right away first: exams may have run out while the server was off.
        $lastAutoSubmitAt = 0.0;

        while ($process->isRunning()) {
            usleep((int) (min(0.5, $this->networkCheckIntervalSeconds) * 1_000_000));

            if (microtime(true) - $lastAutoSubmitAt >= $this->autoSubmitIntervalSeconds) {
                $lastAutoSubmitAt = microtime(true);
                $this->submitExpiredExams();
            }

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
     * Hand in the exams whose time ran out while the page was closed, offline or out of battery.
     */
    protected function submitExpiredExams(): void
    {
        try {
            $submitted = app(SubmitExpiredAttempts::class)->handle();
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if ($submitted > 0) {
            $this->output->writeln('<fg=green>✓ '.$submitted.' ujian yang waktunya habis dikirim otomatis ('.date('H:i:s').').</>');
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
        $this->resolvedHosts = [];
        $this->newLine();
        $this->output->writeln('<bg=yellow;fg=black;options=bold> ⚠️  ALAMAT IP SERVER BERUBAH ('.date('H:i:s').') </>');

        if (empty($interfaces)) {
            $this->output->writeln('   <fg=red>Tidak ada jaringan aktif. Periksa Wi-Fi / kabel LAN server.</>');
        } elseif ($host === '0.0.0.0') {
            $this->output->writeln('   Server tetap berjalan. Jawaban siswa aman (tersimpan di perangkat dan di server).');
            $this->output->writeln('   Bagikan URL baru ini ke siswa (lalu login ulang, jawaban akan dipulihkan otomatis):');
            $this->outputLanHost($interfaces, $port, '   ');
            foreach ($interfaces as $label => $ip) {
                $this->output->writeln("      👉 <fg=cyan;options=bold>http://{$ip}:{$port}</> <fg=gray>({$label})</>");
            }
        } elseif (in_array($host, $interfaces, true)) {
            // Bound to one address with --host: only that address keeps serving the exam.
            $this->output->writeln("   Server hanya melayani <fg=cyan;options=bold>http://{$host}:{$port}</> (alamat --host) dan alamat itu masih aktif.");
            $this->output->writeln('   Alamat lain baru bisa dipakai setelah server dijalankan ulang dengan <fg=yellow>php artisan lan:serve --host=0.0.0.0</>');
        } else {
            $this->output->writeln("   <fg=red;options=bold>Server terikat ke {$host} yang sudah tidak tersedia, siswa tidak dapat terhubung.</>");
            $this->output->writeln('   Jalankan ulang dengan <fg=yellow>php artisan lan:serve --host=0.0.0.0</>, setelah itu siswa dapat memakai:');
            foreach ($interfaces as $label => $ip) {
                $this->output->writeln("      👉 <fg=cyan;options=bold>http://{$ip}:{$port}</> <fg=gray>({$label}, setelah server dijalankan ulang)</>");
            }
        }

        $this->output->writeln('   💡 Agar IP tidak berubah lagi: atur DHCP Reservation (IP tetap) untuk server ini di router.');
        $this->newLine();
    }

    /**
     * PHP settings of the LAN server: large enough for ExamView ZIP packages (64MB), question
     * videos (40MB) and image processing, whatever the php.ini of the machine says.
     *
     * @return list<string>
     */
    protected function phpRuntimeOptions(): array
    {
        return [
            '-d', 'upload_max_filesize=64M',
            '-d', 'post_max_size=80M',
            '-d', 'memory_limit=512M',
            '-d', 'max_file_uploads=50',
        ];
    }

    /**
     * Metadata of the background (daemon) server.
     */
    protected function infoFilePath(): string
    {
        return storage_path('framework/lan_server.json');
    }

    /**
     * After "git pull" the database and Laravel's caches may still match the old code, which
     * breaks pages that use a new column or route (HTTP 500). Bring both up to date first.
     */
    protected function syncWithCurrentCode(): void
    {
        $pendingMigrations = app(PendingMigrations::class)->names();
        if ($pendingMigrations !== []) {
            $this->info('⚙️  Menerapkan '.count($pendingMigrations).' migrasi database baru...');
            $this->call('migrate', ['--force' => true]);
        }

        if ($this->laravel->routesAreCached()) {
            $this->callSilent('route:cache');
            $this->info('✓ Cache routing diperbarui sesuai kode terbaru.');
        }

        if ($this->laravel->configurationIsCached()) {
            $this->callSilent('config:cache');
            $this->info('✓ Cache konfigurasi diperbarui sesuai kode terbaru.');
        }
    }

    protected function manifestPath(): string
    {
        return public_path('build/manifest.json');
    }

    /**
     * The files the bundle in public/build is built from.
     *
     * @return list<string>
     */
    protected function assetSourcePaths(): array
    {
        return array_map(fn (string $path): string => base_path($path), ['resources/js', 'resources/css', 'vite.config.ts', 'tailwind.config.js', 'package.json']);
    }

    /**
     * Whether the bundle is older than its sources: after "git pull" or a merge without "npm run
     * build", browsers would still get the previous pages, whose new buttons do nothing.
     */
    protected function assetsAreOutdated(): bool
    {
        $builtAt = @filemtime($this->manifestPath());

        if ($builtAt === false) {
            return true;
        }

        foreach ($this->assetSourcePaths() as $path) {
            if (is_file($path) && filemtime($path) > $builtAt) {
                return true;
            }

            if (! is_dir($path)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && $file->getMTime() > $builtAt) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Make sure the database (PostgreSQL) answers before students start the exam.
     */
    protected function databaseIsReachable(): bool
    {
        $name = (string) config('database.default');

        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable $e) {
            $driver = (string) (config("database.connections.{$name}.driver") ?? $name);
            $this->error("Database {$driver} tidak dapat dihubungi: {$e->getMessage()}");
            $this->line('   Pastikan layanan PostgreSQL berjalan (Herd -> Services -> PostgreSQL, atau: brew services start postgresql@16)');
            $this->line('   dan pengaturan DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD di berkas .env sudah benar.');

            return false;
        }
    }

    /**
     * Start the server as a background daemon process.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function handleDaemon(int $port, string $host, int $workers, array $interfaces): int
    {
        $logPath = storage_path('logs/lan_server.log');
        $infoFile = $this->infoFilePath();

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

        $runtimeOptions = implode(' ', array_map('escapeshellarg', $this->phpRuntimeOptions()));

        if ($useCaffeinate) {
            $execCmd = "/usr/bin/caffeinate -dimsu {$phpBinary} {$runtimeOptions} -S {$host}:{$port} {$serverScript}";
        } else {
            $execCmd = "{$phpBinary} {$runtimeOptions} -S {$host}:{$port} {$serverScript}";
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
            'addresses' => $this->addressList($interfaces),
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

        $this->outputDaemonBanner($interfaces, $port, $workers, $activePid, $useCaffeinate, $logPath);

        return self::SUCCESS;
    }

    /**
     * Stop the background daemon server.
     */
    protected function handleStop(): int
    {
        $infoFile = $this->infoFilePath();
        $port = (int) $this->option('port');
        $data = file_exists($infoFile) ? json_decode((string) file_get_contents($infoFile), true) : null;
        $serverPort = (int) ($data['port'] ?? $port);

        // No background server, or another port in use next to it (such as a server left on the old
        // port 8000): stop what runs on the port asked for, never the background server.
        if (! is_array($data) || ($port !== $serverPort && $this->isPortInUse($port))) {
            if (! $this->isPortInUse($port)) {
                $this->info('ℹ️  Tidak ada server latar belakang yang sedang aktif.');

                return self::SUCCESS;
            }

            $pids = $this->getPortPids($port);
            if (! $this->isAlsenformServer($pids)) {
                $this->warn("⚠️  Port {$port} sedang digunakan, tetapi bukan oleh server Falcon Forms ini.");
                $this->line('   Demi keamanan, tidak ada proses yang dihentikan. Periksa proses tersebut secara manual.');
                $this->explainBusyPort($port, []);

                return self::FAILURE;
            }

            // A foreground server left in another terminal window, or one whose metadata is gone.
            if (! $this->killPortProcesses($port)) {
                $this->error("Gagal menghentikan server Falcon Forms pada port {$port}.");
                $this->explainBusyPort($port, $pids);

                return self::FAILURE;
            }

            $this->info("✓ Server Falcon Forms pada port {$port} telah dihentikan.");

            return self::SUCCESS;
        }

        $pid = (int) ($data['pid'] ?? 0);

        if ($pid > 0) {
            @shell_exec("kill -TERM {$pid} 2>/dev/null");
            usleep(200 * 1000);
            @shell_exec("kill -9 {$pid} 2>/dev/null");
        }

        $this->killPortProcesses($serverPort);
        @unlink($infoFile);

        $this->newLine();
        $this->output->writeln('<bg=green;fg=white;options=bold> ========================================================================= </>');
        $this->output->writeln('<bg=green;fg=white;options=bold>   🛑 SERVER FALCON FORMS LATAR BELAKANG BERHASIL DIHENTIKAN              </>');
        $this->output->writeln('<bg=green;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();
        $this->info("✓ Server pada port {$serverPort} telah dihentikan.");
        $this->info('✓ Proteksi Anti-Sleep dilepas. Pengaturan daya komputer server kembali ke mode normal.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Everything outside the code that keeps the intranet address from working, checked on this
     * computer and on the school network the way a student's device sees it, with the fix for each.
     */
    protected function handleCheck(): int
    {
        $port = (int) $this->option('port');
        $lanHost = $this->lanHost();
        $interfaces = $this->detectNetworkInterfaces();
        $serverIp = $interfaces !== [] ? (string) reset($interfaces) : null;
        $this->checkProblems = 0;

        $this->newLine();
        $this->output->writeln(' <options=bold>🔎 PEMERIKSAAN ALAMAT INTRANET</> <fg=cyan;options=bold>'.($lanHost !== null ? $this->urlFor($lanHost, $port) : "port {$port}").'</>');
        $this->newLine();

        $this->output->writeln(' <options=bold>Komputer server</>');

        $commit = trim((string) @shell_exec('git -C '.escapeshellarg(base_path()).' log -1 --format="%h %s" 2>/dev/null'));
        if ($commit !== '') {
            $this->checkLine(true, "Kode aplikasi: {$commit}");
        }

        if (! file_exists($this->manifestPath())) {
            $this->checkLine(false, 'Tampilan belum di-build (public/build kosong): halaman tidak dapat tampil.', ['Jalankan: npm run build']);
        } elseif ($this->assetsAreOutdated()) {
            $this->checkLine(false, 'Tampilan lebih lama daripada kode (setelah git pull): tombol dan perbaikan baru belum muncul.', ['Jalankan: npm run build, lalu jalankan ulang server']);
        } else {
            $this->checkLine(true, 'Tampilan sudah di-build sesuai kode terbaru (public/build).');
        }

        $hotUrl = $this->viteHotUrl();
        if ($hotUrl !== null) {
            $this->isListening($hotUrl)
                ? $this->checkLine(null, "npm run dev sedang berjalan ({$hotUrl}): halaman memakai server pengembangan, bukan hasil build.", ['Untuk ujian, hentikan npm run dev (Ctrl+C) agar halaman memakai hasil build.'])
                : $this->checkLine(false, "Berkas public/hot tertinggal dari npm run dev: halaman mencari tampilan di {$hotUrl} yang sudah mati, jadi tampil putih atau FF-201.", ['Hapus berkas public/hot (php artisan lan:serve kini menghapusnya otomatis).']);
        }

        if ($lanHost === null) {
            $this->checkLine(null, 'LAN_HOST di .env kosong atau tidak valid: alamat intranet dimatikan, siswa hanya bisa memakai alamat IP.', ['Isi di .env: LAN_HOST=falcon.lan']);
        }

        $expectedUrl = $lanHost !== null ? $this->urlFor($lanHost, $port) : null;
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($expectedUrl !== null && $appUrl !== $expectedUrl) {
            $this->checkLine(null, "APP_URL={$appUrl}: belum alamat baru.", ["Ubah di .env: APP_URL={$expectedUrl}"]);
        } elseif ($expectedUrl !== null) {
            $this->checkLine(true, "APP_URL={$appUrl}");
        }

        foreach ($this->sessionCookieWarnings() as $warning) {
            $this->checkLine(false, $warning);
        }

        $isServing = false;
        if (! $this->isPortInUse($port)) {
            $this->checkLine(false, "Server belum berjalan di port {$port}.", ['Jalankan: php artisan lan:serve']);
        } elseif (! $this->isAlsenformServer($this->getPortPids($port))) {
            $this->checkLine(false, "Port {$port} dipakai program lain, bukan Falcon Forms.", ["Hentikan program itu, atau jalankan: php artisan lan:serve --force (menghentikan pemakai port {$port})"]);
        } else {
            $isServing = true;
            $this->checkLine(true, "Server Falcon Forms berjalan di port {$port}.");
        }

        if ($port !== self::LEGACY_PORT && $this->isPortInUse(self::LEGACY_PORT) && $this->isAlsenformServer($this->getPortPids(self::LEGACY_PORT))) {
            $this->checkLine(false, 'Server lama masih berjalan di port '.self::LEGACY_PORT.': perangkat yang membuka alamat lama tetap memakainya.', ['Setelah ujian di alamat lama selesai: php artisan lan:stop --port='.self::LEGACY_PORT]);
        }

        if ($isServing && $serverIp !== null) {
            $status = $this->httpStatus("http://{$serverIp}:{$port}/up");
            $status === 200
                ? $this->checkLine(true, "Server menjawab di http://{$serverIp}:{$port}")
                : $this->checkLine(false, "Server tidak menjawab dengan benar di http://{$serverIp}:{$port} (".($status !== null ? "HTTP {$status}" : 'tidak ada jawaban').').', ['Lihat pesan galat di terminal lan:serve atau storage/logs/laravel.log']);
        }

        foreach ($this->firewallProblems() as [$problem, $fix]) {
            $this->checkLine(false, $problem, [$fix]);
        }

        $this->newLine();
        $this->output->writeln(' <options=bold>Jaringan sekolah</>');

        if ($interfaces === []) {
            $this->checkLine(false, 'Server tidak tersambung ke jaringan mana pun.', ['Sambungkan kabel LAN atau Wi-Fi sekolah.']);
        } else {
            $this->checkLine(true, 'IP server: '.implode(', ', array_map(fn (string $label, string $ip): string => "{$ip} ({$label})", array_keys($interfaces), $interfaces)));
        }

        if ($lanHost !== null && $serverIp !== null) {
            $routerKnows = $this->checkRouterDns($lanHost, $interfaces, $serverIp);
            $this->checkDhcpDns($lanHost, $interfaces, $serverIp, $routerKnows);

            $ownAddress = $this->resolveHost($lanHost);
            if ($ownAddress !== null && in_array($ownAddress, $interfaces, true)) {
                $status = $isServing ? $this->httpStatus($this->urlFor($lanHost, $port).'/up') : null;
                $this->checkLine($isServing ? $status === 200 : null, $isServing && $status === 200
                    ? $this->urlFor($lanHost, $port).' terbuka dari komputer server ini.'
                    : "Komputer server mengenal {$lanHost} → {$ownAddress}, tetapi halamannya belum terbuka.");
            } else {
                $this->checkLine(null, "Komputer server sendiri belum mengenal {$lanHost} (DNS-nya bukan router). Siswa tidak terpengaruh; di komputer ini pakai http://localhost:{$port}.");
            }
        }

        $this->newLine();
        $this->output->writeln(' <options=bold>Di perangkat siswa</> <fg=gray>(tidak bisa diperiksa dari sini)</>');
        $this->output->writeln('   • Ketik alamat lengkap: <fg=cyan>'.($expectedUrl ?? "http://IP-server:{$port}").'</> (dengan http:// dan port), lalu simpan sebagai bookmark.');
        $this->output->writeln('   • Matikan "DNS Pribadi" (Android), "DNS aman" di Chrome, VPN, atau iCloud Private Relay: semuanya melewati DNS router.');
        $this->output->writeln('   • Setelah pengaturan router diubah, matikan lalu nyalakan Wi-Fi perangkat agar jawaban DNS lama terlupa.');
        if ($serverIp !== null) {
            $this->output->writeln("   • Alamat IP selalu bisa dipakai sebagai cadangan: <fg=cyan>http://{$serverIp}:{$port}</>");
        }

        $this->newLine();
        if ($this->checkProblems === 0) {
            $this->output->writeln(' <fg=green;options=bold>✓ Tidak ada masalah yang ditemukan di server dan router.</>');

            return self::SUCCESS;
        }

        $this->output->writeln(" <fg=red;options=bold>✗ {$this->checkProblems} masalah perlu diperbaiki (lihat tanda ✗ di atas).</>");

        return self::FAILURE;
    }

    /**
     * One line of the check: passed (true), a problem (false) or worth knowing (null), with its fixes.
     *
     * @param  list<string>  $fixes
     */
    protected function checkLine(?bool $passed, string $text, array $fixes = []): void
    {
        if ($passed === false) {
            $this->checkProblems++;
        }

        $mark = match ($passed) {
            true => '<fg=green>✓</>',
            false => '<fg=red;options=bold>✗</>',
            null => '<fg=yellow>⚠</>',
        };
        $this->output->writeln("   {$mark} {$text}");

        foreach ($fixes as $fix) {
            $this->output->writeln("      <fg=yellow>→ {$fix}</>");
        }
    }

    /**
     * Whether the router answers the intranet name with the server's address, asked the way
     * a student's device asks it.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function checkRouterDns(string $lanHost, array $interfaces, string $serverIp): bool
    {
        $gateway = $this->defaultGateway();
        if ($gateway === null) {
            $this->checkLine(null, 'Router (default gateway) tidak ditemukan, DNS router tidak dapat diperiksa.');

            return false;
        }

        $answer = $this->lookupAt($gateway, $lanHost);
        $addHint = "Tambahkan DNS statis di router: {$lanHost} → {$serverIp}. MikroTik: IP → DNS → Static; OpenWrt: Network → DHCP and DNS → Hostnames; pfSense/OPNsense: DNS Resolver → Host Overrides; router lain: menu Static DNS / Local DNS.";

        $knows = $answer['status'] === DnsLookup::FOUND && array_intersect($answer['addresses'], $interfaces) !== [];

        match (true) {
            $knows => $this->checkLine(true, "Router {$gateway} mengenal {$lanHost} → ".implode(', ', $answer['addresses'])),
            $answer['status'] === DnsLookup::FOUND => $this->checkLine(false, "Router {$gateway} mengarahkan {$lanHost} ke ".implode(', ', $answer['addresses']).", bukan ke server ini ({$serverIp}).", ["Ubah DNS statis di router: {$lanHost} → {$serverIp}", 'Atur juga DHCP Reservation (IP tetap) untuk server ini agar tidak berubah lagi.']),
            $answer['status'] === DnsLookup::UNKNOWN => $this->checkLine(false, "Router {$gateway} belum mengenal {$lanHost}: perangkat siswa tidak bisa membuka alamat ini.", [$addHint, 'Atur juga DHCP Reservation (IP tetap) untuk server ini.', 'Modem bawaan penyedia internet sering tidak punya menu DNS statis: pakai router sendiri (misalnya MikroTik) di belakangnya, atau bagikan alamat IP ke siswa.']),
            $answer['status'] === DnsLookup::REFUSED => $this->checkLine(false, "Router {$gateway} menolak menjawab DNS untuk perangkat lain.", ['MikroTik: IP → DNS → centang "Allow Remote Requests" (tutup port 53 dari internet di firewall).', $addHint]),
            default => $this->checkLine(null, "Router {$gateway} tidak menjawab DNS: DNS jaringan ini dijalankan perangkat lain.", ["Pasang DNS statis {$lanHost} → {$serverIp} di server DNS yang dibagikan DHCP (lihat baris berikut)."]),
        };

        return $knows;
    }

    /**
     * Whether the DNS servers this computer got from the network know the intranet name: devices
     * on the same Wi-Fi usually get the same ones from DHCP.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function checkDhcpDns(string $lanHost, array $interfaces, string $serverIp, bool $routerKnows): void
    {
        $servers = array_slice($this->dnsServers(), 0, 3);
        if ($servers === []) {
            return;
        }

        $knowing = array_values(array_filter($servers, function (string $server) use ($lanHost, $interfaces): bool {
            $answer = $this->lookupAt($server, $lanHost);

            return $answer['status'] === DnsLookup::FOUND && array_intersect($answer['addresses'], $interfaces) !== [];
        }));

        if ($knowing !== []) {
            $this->checkLine(true, 'DNS yang dibagikan jaringan ('.implode(', ', $servers).") mengenal {$lanHost}.");

            return;
        }

        $this->checkLine(false, 'DNS yang dipakai komputer ini ('.implode(', ', $servers).") tidak mengenal {$lanHost}. Perangkat siswa yang mendapat DNS yang sama dari DHCP juga tidak bisa membukanya.", [
            $routerKnows
                ? 'Di pengaturan DHCP router, isi DNS server dengan '.$this->defaultGateway().', bukan 8.8.8.8 atau DNS penyedia internet.'
                : "Pasang DNS statis {$lanHost} → {$serverIp} di router, lalu di pengaturan DHCP router isi DNS server dengan IP router itu.",
            'Bila DNS komputer server ini diatur manual, abaikan baris ini untuk server; pastikan saja DHCP membagikan DNS router ke perangkat siswa.',
        ]);
    }

    /**
     * The default gateway, usually the school router.
     */
    protected function defaultGateway(): ?string
    {
        $output = PHP_OS_FAMILY === 'Darwin'
            ? (string) @shell_exec('route -n get default 2>/dev/null')
            : (string) @shell_exec('ip -4 route show default 2>/dev/null');

        if (preg_match('/(?:gateway:\s*|default via\s+)(\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
            return $matches[1];
        }

        // Linux without the "ip" tool: the kernel's table, addresses in little-endian hex.
        foreach (@file('/proc/net/route') ?: [] as $line) {
            $columns = preg_split('/\s+/', trim($line)) ?: [];
            if (($columns[1] ?? '') === '00000000' && preg_match('/^[0-9A-F]{8}$/i', $columns[2] ?? '') && $columns[2] !== '00000000') {
                return implode('.', array_reverse(array_map('hexdec', str_split($columns[2], 2))));
            }
        }

        return null;
    }

    /**
     * The DNS servers this computer uses (loopback resolvers left out).
     *
     * @return list<string>
     */
    protected function dnsServers(): array
    {
        $output = PHP_OS_FAMILY === 'Darwin'
            ? (string) @shell_exec('scutil --dns 2>/dev/null')
            : (string) @file_get_contents('/etc/resolv.conf');

        preg_match_all('/nameserver(?:\[\d+\])?\s*:?\s*(\d+\.\d+\.\d+\.\d+)/', $output, $matches);

        return array_values(array_unique(array_filter($matches[1], fn (string $ip): bool => ! str_starts_with($ip, '127.'))));
    }

    /**
     * @return array{status: string, addresses: list<string>}
     */
    protected function lookupAt(string $server, string $name): array
    {
        return app(DnsLookup::class)->query($server, $name);
    }

    /**
     * The HTTP status of a page of this server, or null when it does not answer.
     */
    protected function httpStatus(string $url): ?int
    {
        $context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true, 'header' => "Connection: close\r\n"]]);
        $http_response_header = null;
        @file_get_contents($url, false, $context);
        $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : $http_response_header;

        return is_array($headers) && isset($headers[0]) && preg_match('/^HTTP\/\S+\s+(\d{3})/', $headers[0], $matches) ? (int) $matches[1] : null;
    }

    /**
     * What the macOS firewall blocks of the server, with the fix for each.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function firewallProblems(): array
    {
        $tool = '/usr/libexec/ApplicationFirewall/socketfilterfw';
        if (PHP_OS_FAMILY !== 'Darwin' || ! is_file($tool)) {
            return [];
        }

        if (! preg_match('/enabled/i', (string) @shell_exec("{$tool} --getglobalstate 2>/dev/null"))) {
            return [];
        }

        $problems = [];
        if (preg_match('/block all[^\n]*\benabled\b/i', (string) @shell_exec("{$tool} --getblockall 2>/dev/null"))) {
            $problems[] = ['Firewall macOS memblokir semua koneksi masuk: perangkat siswa tidak dapat terhubung.', 'System Settings → Network → Firewall → Options: matikan "Block all incoming connections".'];
        }

        $php = realpath(PHP_BINARY) ?: PHP_BINARY;
        $state = (string) @shell_exec("{$tool} --getappblocked ".escapeshellarg($php).' 2>/dev/null');
        if (preg_match('/\bblocked\b/i', $state) && ! preg_match('/permitted|allowed|not blocked|not part/i', $state)) {
            $problems[] = ["Firewall macOS memblokir PHP ({$php}): perangkat siswa tidak dapat terhubung.", 'System Settings → Network → Firewall → Options: ubah php menjadi "Allow incoming connections".'];
        }

        return $problems;
    }

    /**
     * The address of the Vite development server written to public/hot by "npm run dev", if any.
     */
    protected function viteHotUrl(): ?string
    {
        $file = $this->viteHotFile();

        return is_file($file) ? (trim((string) file_get_contents($file)) ?: null) : null;
    }

    protected function viteHotFile(): string
    {
        return public_path('hot');
    }

    /**
     * Whether something accepts connections at the host and port of a URL.
     */
    protected function isListening(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? 80;
        if (! is_string($host) || $host === '') {
            return false;
        }

        $connection = @stream_socket_client("tcp://{$host}:{$port}", $errorCode, $errorMessage, 1.0);
        if ($connection === false) {
            return false;
        }
        fclose($connection);

        return true;
    }

    /**
     * public/hot left behind by "npm run dev" makes every page fetch its scripts from a development
     * server that no longer runs: blank pages (FF-201) for everyone. Removed before serving.
     */
    protected function removeStaleViteHotFile(): void
    {
        $hotUrl = $this->viteHotUrl();
        if ($hotUrl === null) {
            return;
        }

        if ($this->isListening($hotUrl)) {
            $this->warn("⚠️  npm run dev masih berjalan ({$hotUrl}): halaman siswa memakai server pengembangan. Untuk ujian, hentikan npm run dev agar halaman memakai hasil build.");

            return;
        }

        @unlink($this->viteHotFile());
        $this->info('✓ Berkas public/hot (sisa npm run dev) dihapus: halaman memakai tampilan hasil build.');
    }

    /**
     * Check status of the background daemon server.
     */
    protected function handleStatus(): int
    {
        $infoFile = $this->infoFilePath();

        if (! file_exists($infoFile)) {
            $port = (int) $this->option('port');
            if ($this->isPortInUse($port)) {
                $this->warn("⚠️  Server berjalan pada port {$port} (tanpa rekaman metadata background).");
            } else {
                $this->info('ℹ️  Server Falcon Forms sedang TIDAK berjalan di latar belakang.');
                $this->line('   Jalankan dengan: <fg=yellow>php artisan lan:serve --daemon</>');
            }

            return self::SUCCESS;
        }

        $data = json_decode((string) file_get_contents($infoFile), true);
        $pid = (int) ($data['pid'] ?? 0);
        $port = (int) ($data['port'] ?? self::DEFAULT_PORT);
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
        $this->output->writeln('<bg=blue;fg=white;options=bold>   🟢 STATUS SERVER FALCON FORMS (BACKGROUND DAEMON)                      </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();
        $this->output->writeln('   • Status           : <fg=green;options=bold>AKTIF / BERJALAN DI LATAR BELAKANG</>');
        $this->output->writeln("   • Process ID (PID) : <fg=cyan;options=bold>{$pid}</>");
        $this->output->writeln("   • Port Jaringan    : <fg=cyan;options=bold>{$port}</>");
        $this->output->writeln('   • Multi-Workers    : <fg=cyan>'.($data['workers'] ?? 24).' concurrent processes</>');
        $this->output->writeln("   • Uptime (Aktif)   : <fg=yellow;options=bold>{$uptimeFormatted}</>");
        $this->output->writeln('   • Anti-Sleep (Mac) : <fg=green;options=bold>AKTIF (Caffeinate - Komputer Server Tidak Akan Sleep/Tidur)</>');
        $this->output->writeln('   • Berkas Log       : <fg=gray>storage/logs/lan_server.log</>');
        $this->newLine();

        $interfaces = $this->detectNetworkInterfaces();
        $startedAddresses = $data['addresses'] ?? null;
        if (is_array($startedAddresses) && $startedAddresses !== $this->addressList($interfaces)) {
            // Daemon mode has no terminal to print to: the change is reported here instead.
            $this->announceNetworkChange($interfaces, (string) ($data['host'] ?? '0.0.0.0'), $port);
            $data['addresses'] = $this->addressList($interfaces);
            @file_put_contents($infoFile, json_encode($data, JSON_PRETTY_PRINT));
        }

        $this->output->writeln('   🌐 <options=bold>Akses URL Siswa (alamat saat ini):</>');
        $this->outputLanHost($interfaces, $port, '   ');
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

        return $this->getPortPids($port) !== [];
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

        // The intranet name when the network's DNS leads it to the address the server listens on.
        $lanHost = $this->lanHost();
        if ($lanHost !== null && $this->lanHostPointsHere($host === '0.0.0.0' ? $interfaces : [$host])) {
            return $this->urlFor($lanHost, $port);
        }

        return $address ? "http://{$address}:{$port}" : null;
    }

    /**
     * The intranet name students type (LAN_HOST, falcon.lan by default), or null when there is none.
     */
    protected function lanHost(): ?string
    {
        $host = strtolower(trim((string) config('app.lan_host')));

        return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $host) ? $host : null;
    }

    /**
     * The IPv4 address the network's DNS gives for a name, or null when it does not know the name.
     */
    protected function resolveHost(string $host): ?string
    {
        if (! array_key_exists($host, $this->resolvedHosts)) {
            $address = @gethostbyname($host);
            $this->resolvedHosts[$host] = $address !== $host && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? $address : null;
        }

        return $this->resolvedHosts[$host];
    }

    /**
     * Whether the intranet name leads to one of these addresses of the server.
     *
     * @param  array<array-key, string>  $addresses
     */
    protected function lanHostPointsHere(array $addresses): bool
    {
        $lanHost = $this->lanHost();
        $address = $lanHost !== null ? $this->resolveHost($lanHost) : null;

        return $address !== null && in_array($address, $addresses, true);
    }

    protected function urlFor(string $host, int $port): string
    {
        return $port === 80 ? "http://{$host}" : "http://{$host}:{$port}";
    }

    /**
     * The intranet address, and what the router still needs when the name does not lead here yet.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function outputLanHost(array $interfaces, int $port, string $indent = ''): void
    {
        $lanHost = $this->lanHost();
        if ($lanHost === null) {
            return;
        }

        $url = $this->urlFor($lanHost, $port);
        $address = $this->resolveHost($lanHost);
        $serverIp = $interfaces !== [] ? reset($interfaces) : 'IP server ini';

        if ($address !== null && in_array($address, $interfaces, true)) {
            $this->output->writeln("{$indent}   👉 <fg=green;options=bold>{$url}</> <fg=gray>(alamat intranet → {$address})</>");

            return;
        }

        $this->output->writeln("{$indent}   👉 <fg=yellow;options=bold>{$url}</> <fg=gray>(alamat intranet, belum aktif)</>");
        $this->output->writeln($address !== null
            ? "{$indent}      <fg=yellow>⚠️  {$lanHost} masih mengarah ke {$address}, bukan ke server ini. Ubah DNS statisnya di router: {$lanHost} → {$serverIp}</>"
            : "{$indent}      <fg=yellow>⚠️  Router belum mengenal {$lanHost}. Tambahkan DNS statis di router: {$lanHost} → {$serverIp}</>");
        $this->output->writeln("{$indent}      <fg=gray>Atur juga DHCP Reservation (IP tetap) untuk server ini. Sampai saat itu, pakai alamat IP di bawah.</>");
        $this->output->writeln("{$indent}      <fg=gray>Pemeriksaan lengkap beserta cara memperbaikinya:</> <fg=yellow>php artisan lan:check</>");
    }

    /**
     * Retrieve the IDs of the processes listening on the given port. Programs only connected
     * to it, such as a browser that has the exam open, are left out so they are never killed.
     *
     * @return list<string>
     */
    public function getPortPids(int $port): array
    {
        $output = @shell_exec("lsof -nP -iTCP:{$port} -sTCP:LISTEN -t 2>/dev/null");
        if (! $output) {
            return [];
        }

        $pids = array_filter(array_map('trim', explode("\n", (string) $output)), 'ctype_digit');

        return array_values(array_unique($pids));
    }

    /**
     * Terminate the processes listening on the given port: politely first (SIGTERM), then
     * forcefully (SIGKILL), waiting until the port is really free. A server with 24 workers
     * needs a moment to release it.
     */
    public function killPortProcesses(int $port): bool
    {
        $pids = $this->getPortPids($port);
        if ($pids === []) {
            return ! $this->isPortInUse($port);
        }

        $this->signalProcesses($pids, 'TERM');
        if ($this->waitUntilPortIsFree($port, 2.0)) {
            return true;
        }

        $this->signalProcesses($this->getPortPids($port), 'KILL');

        return $this->waitUntilPortIsFree($port, 3.0);
    }

    /**
     * @param  list<string>  $pids
     */
    protected function signalProcesses(array $pids, string $signal): void
    {
        if ($pids !== []) {
            @shell_exec("kill -{$signal} ".implode(' ', $pids).' 2>/dev/null');
        }
    }

    protected function waitUntilPortIsFree(int $port, float $seconds): bool
    {
        $deadline = microtime(true) + $seconds;

        do {
            if (! $this->isPortInUse($port)) {
                return true;
            }
            usleep(100 * 1000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /**
     * "PID PPID USER COMMAND" of each process, as listed by ps.
     *
     * @param  list<string>  $pids
     * @return list<string>
     */
    protected function describeProcesses(array $pids): array
    {
        if ($pids === []) {
            return [];
        }

        $selection = implode(' ', array_map(fn (string $pid): string => "-p {$pid}", $pids));
        $output = (string) @shell_exec("ps -ww -o pid=,ppid=,user=,command= {$selection} 2>/dev/null");

        return array_values(array_filter(array_map('trim', explode("\n", $output))));
    }

    /**
     * Whether every given process is the PHP server of this installation (its own server.php),
     * so it may be stopped even without the metadata file of the background mode.
     *
     * @param  list<string>  $pids
     */
    protected function isAlsenformServer(array $pids): bool
    {
        $processes = $this->describeProcesses($pids);
        $serverScript = base_path('server.php');

        return $processes !== []
            && count($processes) === count($pids)
            && collect($processes)->every(fn (string $process): bool => str_contains($process, $serverScript));
    }

    /**
     * Tell the operator which processes still hold the port and how to free it by hand.
     *
     * @param  list<string>  $stoppedPids
     */
    protected function explainBusyPort(int $port, array $stoppedPids): void
    {
        $remainingPids = $this->getPortPids($port);
        $processes = $this->describeProcesses($remainingPids);

        if ($processes !== []) {
            $this->line('   Proses yang masih memakai port (PID, PID induk, pemilik, perintah):');
            foreach (array_slice($processes, 0, 5) as $process) {
                $this->line('      <fg=gray>'.mb_strimwidth($process, 0, 140, '…').'</>');
            }
            if (count($processes) > 5) {
                $this->line('      <fg=gray>… dan '.(count($processes) - 5).' proses lain</>');
            }
        }

        if ($stoppedPids !== [] && $remainingPids !== [] && array_intersect($remainingPids, $stoppedPids) === []) {
            $this->line('   Proses baru muncul lagi setelah dihentikan: server ini dijalankan ulang otomatis oleh program lain');
            $this->line('   (misalnya jendela Terminal lain, Login Items, atau launchd). Hentikan dari sana terlebih dahulu.');
        }

        $this->line('   Proses milik pengguna lain (misalnya server yang dulu dijalankan dengan sudo) hanya bisa dihentikan dengan:');
        $this->line("      <fg=yellow>sudo kill -9 $(sudo lsof -nP -iTCP:{$port} -sTCP:LISTEN -t)</>");
        $this->line('   Atau jalankan di port lain: <fg=yellow>php artisan lan:serve --port='.($port + 1).'</>');
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
            $this->explainBusyPort($port, $pids);

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
                $this->explainBusyPort($port, $pids);

                $newPort = $this->findAvailablePort($port + 1);
                if ($this->confirm("Jalankan server di port {$newPort} saja?", true)) {
                    $this->info("✓ Menggunakan port baru yang tersedia: {$newPort}");

                    return $newPort;
                }
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
     * Where to check a slow device, and the server's network set-ups that make pages slow.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function outputNetworkAdvice(array $interfaces, int $port): void
    {
        $lanHost = $this->lanHost();
        $baseUrl = $lanHost !== null && $this->lanHostPointsHere($interfaces)
            ? $this->urlFor($lanHost, $port)
            : 'http://'.($interfaces !== [] ? reset($interfaces) : 'localhost').":{$port}";
        $this->output->writeln(" <fg=white;options=bold>🩺 Perangkat terasa lambat?</> buka <fg=cyan>{$baseUrl}/cek-koneksi</> di perangkat itu.");

        foreach ([...$this->networkWarnings($interfaces), ...$this->sessionCookieWarnings()] as $warning) {
            $this->output->writeln(" <fg=yellow;options=bold>⚠️  {$warning}</>");
        }

        $this->newLine();
    }

    /**
     * Set-ups of the server that make pages slow or drop connections for everyone using it.
     *
     * @param  array<string, string>  $interfaces  Address by interface label.
     * @return list<string>
     */
    protected function networkWarnings(array $interfaces): array
    {
        $isWifi = fn (string $label): bool => (bool) preg_match('/wi-?fi|airport|wlan/i', $label);
        $wifiLabels = array_filter(array_keys($interfaces), $isWifi);
        $warnings = [];

        if ($interfaces !== [] && count($wifiLabels) === count($interfaces)) {
            $warnings[] = 'Server hanya terhubung lewat Wi-Fi: semua siswa berbagi satu radio Wi-Fi server. Sambungkan server ke router/access point dengan kabel LAN agar halaman tidak lambat atau terputus.';
        }

        if ($wifiLabels !== [] && $this->isAwdlActive()) {
            $warnings[] = 'AirDrop/Handoff (AWDL) aktif di server: Wi-Fi Mac tersendat setiap beberapa detik. Matikan AirDrop dan Handoff selama ujian, atau jalankan: sudo ifconfig awdl0 down';
        }

        return $warnings;
    }

    /**
     * Session cookie settings that stop logins over http://falcon.lan:2026 and the IP addresses:
     * the browser drops the cookie, and every login or form ends in "419 Page Expired".
     *
     * @return list<string>
     */
    protected function sessionCookieWarnings(): array
    {
        $warnings = [];
        $domain = trim((string) config('session.domain'));

        if ($domain !== '') {
            $warnings[] = "SESSION_DOMAIN={$domain} di .env: browser menolak cookie login di alamat lain (falcon.lan, alamat IP), jadi login gagal (419). Kosongkan baris itu (SESSION_DOMAIN=null) lalu jalankan ulang server.";
        }

        if (config('session.secure') === true) {
            $warnings[] = 'SESSION_SECURE_COOKIE=true di .env: cookie login hanya dikirim lewat HTTPS, padahal server ini memakai http://, jadi login gagal (419). Ubah menjadi SESSION_SECURE_COOKIE=false lalu jalankan ulang server.';
        }

        return $warnings;
    }

    /**
     * A Falcon Forms server still running on the old port keeps the old address alive, and students
     * would carry on there. It is stopped when the operator agrees (an exam may still be running
     * on it); unattended or declined, the command to stop it later is shown.
     */
    protected function retireLegacyServer(int $port): void
    {
        if ($port === self::LEGACY_PORT || ! $this->isPortInUse(self::LEGACY_PORT)) {
            return;
        }

        $pids = $this->getPortPids(self::LEGACY_PORT);
        if (! $this->isAlsenformServer($pids)) {
            return;
        }

        $legacyPort = self::LEGACY_PORT;
        $this->warn("⚠️  Server Falcon Forms lama masih berjalan di port {$legacyPort} (alamat lama, misalnya http://alsenform.local:{$legacyPort}).");

        $shouldStop = $this->option('force')
            || ($this->input->isInteractive() && $this->confirm("Hentikan server lama itu sekarang? Siswa yang masih membukanya perlu pindah ke alamat baru (port {$port}).", true));

        if (! $shouldStop) {
            $this->line("   Server lama dibiarkan berjalan. Setelah ujian di alamat lama selesai, hentikan dengan: <fg=yellow>php artisan lan:stop --port={$legacyPort}</>");

            return;
        }

        if (! $this->killPortProcesses(self::LEGACY_PORT)) {
            $this->error("Gagal menghentikan server lama di port {$legacyPort}.");
            $this->explainBusyPort(self::LEGACY_PORT, $pids);

            return;
        }

        $infoFile = $this->infoFilePath();
        $info = is_file($infoFile) ? json_decode((string) file_get_contents($infoFile), true) : null;
        if (is_array($info) && (int) ($info['port'] ?? 0) === self::LEGACY_PORT) {
            @unlink($infoFile);
        }

        $this->info("✓ Server lama di port {$legacyPort} dihentikan. Alamat lama tidak dipakai lagi.");
    }

    /**
     * Whether macOS AWDL (AirDrop, Handoff, Sidecar) is active: it takes the Wi-Fi radio to other
     * channels several times a second, which delays and drops the server's Wi-Fi traffic.
     */
    protected function isAwdlActive(): bool
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return false;
        }

        return str_contains((string) @shell_exec('ifconfig awdl0 2>/dev/null'), 'status: active');
    }

    /**
     * Print banner when running as background daemon.
     *
     * @param  array<string, string>  $interfaces
     */
    protected function outputDaemonBanner(array $interfaces, int $port, int $workers, int $pid, bool $useCaffeinate, string $logPath): void
    {
        $this->newLine();
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold>   🚀 FALCON FORMS BERJALAN DI LATAR BELAKANG (BACKGROUND DAEMON)        </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();

        $this->output->writeln(" <fg=green;options=bold>✓ Status Server:</> <fg=green;options=bold>AKTIF</> (PID: <fg=cyan;options=bold>{$pid}</>)");
        $this->output->writeln(" <fg=green;options=bold>✓ Multi-Workers:</> <fg=cyan>{$workers} concurrent processes</> (mencegah antrean)");
        if ($useCaffeinate) {
            $this->output->writeln(' <fg=green;options=bold>✓ Anti-Sleep Mac:</> <fg=cyan;options=bold>AKTIF (Caffeinate)</> - komputer server tidak akan tidur selama server aktif');
        }
        $this->output->writeln(" <fg=green;options=bold>✓ Berkas Log:</> <fg=gray>{$logPath}</>");
        $this->newLine();

        $this->output->writeln(' <fg=yellow;options=bold>🌐 URL Akses untuk Siswa, Guru & Pengawas:</>');
        $this->outputLanHost($interfaces, $port);
        if (! empty($interfaces)) {
            foreach ($interfaces as $label => $ip) {
                $this->output->writeln("   👉 <fg=cyan;options=bold>http://{$ip}:{$port}</> <fg=gray>({$label})</>");
            }
        } else {
            $this->output->writeln("   👉 <fg=cyan;options=bold>http://0.0.0.0:{$port}</>");
        }
        $this->newLine();
        $this->outputNetworkAdvice($interfaces, $port);

        $this->output->writeln(' <fg=white;options=bold>🕹️ Perintah Manajemen Server:</>');
        $this->output->writeln('   • Cek status server : <fg=yellow>php artisan lan:status</> atau <fg=yellow>php artisan lan:serve --status</>');
        $this->output->writeln('   • Jika IP berubah   : mode latar belakang tidak dapat memberi tahu otomatis; jalankan <fg=yellow>php artisan lan:status</> untuk melihat URL terbaru');
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
    protected function outputBanner(array $interfaces, int $port, int $workers = 24, bool $useCaffeinate = true): void
    {
        $this->newLine();
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold>   🚀 FALCON FORMS - SIAP DIGUNAKAN DI JARINGAN LOKAL (LAN / WI-FI)     </>');
        $this->output->writeln('<bg=blue;fg=white;options=bold> ========================================================================= </>');
        $this->newLine();

        $this->output->writeln(' <fg=green;options=bold>✓ Performa & Kapasitas Jaringan:</>');
        $this->output->writeln("   ⚡ <options=bold>Multi-Workers</>  : <fg=cyan>{$workers} concurrent processes</> (anti antrean request)");
        $this->output->writeln('   ⚡ <options=bold>Kompresi Gzip</>  : <fg=cyan>Aktif</> (menghemat bandwidth intranet ~80%)');
        $this->output->writeln('   ⚡ <options=bold>Cache Aset VITE</>: <fg=cyan>Aktif (Immutable)</> (0-byte reload di browser siswa)');
        $this->output->writeln('   ⚡ <options=bold>Kompresi Media</> : <fg=cyan>WebP Auto-Scale</> (gambar otomatis < 300KB)');
        if ($useCaffeinate) {
            $this->output->writeln('   ⚡ <options=bold>Anti-Sleep (Mac)</>: <fg=cyan;options=bold>AKTIF (Caffeinate)</> (komputer server tidak akan tidur selama server aktif)');
        }
        $this->newLine();

        $this->output->writeln(' <fg=green;options=bold>✓ Jaringan & Frekuensi yang Didukung:</>');
        $this->output->writeln('   📶 <options=bold>Wi-Fi 5 GHz</>     : Sangat cepat & latensi rendah (sangat direkomendasikan)');
        $this->output->writeln('   📶 <options=bold>Wi-Fi 2.4 GHz</>   : Jangkauan lebih luas untuk perangkat di kelas/ruang jauh');
        $this->output->writeln('   🔌 <options=bold>Kabel LAN (RJ45)</>: Koneksi paling stabil untuk komputer server/lab');
        $this->newLine();

        $this->output->writeln(' <fg=yellow;options=bold>🌐 URL Akses untuk Siswa, Guru & Pengawas:</>');
        $this->outputLanHost($interfaces, $port);
        if (! empty($interfaces)) {
            foreach ($interfaces as $label => $ip) {
                $this->output->writeln("   👉 <fg=cyan;options=bold>http://{$ip}:{$port}</> <fg=gray>({$label})</>");
            }
        } else {
            $this->output->writeln("   👉 <fg=cyan;options=bold>http://0.0.0.0:{$port}</>");
        }

        $this->output->writeln("   👉 <fg=white>http://localhost:{$port}</> <fg=gray>(Hanya di komputer ini)</>");

        $this->newLine();
        $this->outputNetworkAdvice($interfaces, $port);
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
