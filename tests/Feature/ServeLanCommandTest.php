<?php

use App\Console\Commands\ServeLanCommand;
use App\Support\PendingMigrations;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

test('lan:serve command is registered and outputs description and force option', function () {
    $this->artisan('lan:serve --help')
        ->assertSuccessful()
        ->expectsOutputToContain('Jalankan aplikasi Falcon Forms di jaringan lokal (Wi-Fi 5GHz, 2.4GHz, dan Kabel LAN)')
        ->expectsOutputToContain('--force')
        ->expectsOutputToContain('--daemon')
        ->expectsOutputToContain('--status')
        ->expectsOutputToContain('--stop')
        ->expectsOutputToContain('--no-caffeinate');
});

test('lan:serve rejects unsafe host and invalid port values', function () {
    $this->artisan('lan:serve --host="0.0.0.0; echo unsafe"')
        ->assertExitCode(2);

    $this->artisan('lan:serve --port=0')
        ->assertExitCode(2);
});

test('lan:serve detectNetworkInterfaces returns array', function () {
    $command = new class extends ServeLanCommand
    {
        public function testInterfaces(): array
        {
            return $this->detectNetworkInterfaces();
        }
    };

    $interfaces = $command->testInterfaces();
    expect($interfaces)->toBeArray();
});

test('lan:serve port utility methods work correctly', function () {
    $command = new ServeLanCommand;

    // A randomly high unassigned port should not be in use
    $freePort = 59123;
    expect($command->isPortInUse($freePort))->toBeFalse();

    $foundPort = $command->findAvailablePort($freePort);
    expect($foundPort)->toBe($freePort);

    expect($command->getPortPids($freePort))->toBeArray();
});

/**
 * Start a PHP server with workers, like lan:serve does, and wait until it listens.
 *
 * @param  list<string>  $arguments
 */
function startLanTestServer(int $port, array $arguments): Process
{
    $process = new Process([PHP_BINARY, '-S', "127.0.0.1:{$port}", ...$arguments], base_path(), ['PHP_CLI_SERVER_WORKERS' => '2']);
    $process->start();

    $deadline = microtime(true) + 5;
    while (microtime(true) < $deadline && (new ServeLanCommand)->getPortPids($port) === []) {
        usleep(50 * 1000);
    }

    return $process;
}

function lanCommandWithoutMetadata(): ServeLanCommand
{
    return new class extends ServeLanCommand
    {
        protected function infoFilePath(): string
        {
            return storage_path('framework/testing/lan_server_missing.json');
        }

        public function stop(): int
        {
            return $this->handleStop();
        }
    };
}

test('lan:serve stops the server on the port without touching programs connected to it', function () {
    $port = 59140;
    $server = startLanTestServer($port, [base_path('server.php')]);
    $client = stream_socket_client("tcp://127.0.0.1:{$port}");

    try {
        $command = new ServeLanCommand;
        $pids = $command->getPortPids($port);

        expect($pids)->toContain((string) $server->getPid())
            ->toHaveCount(3)
            ->not->toContain((string) getmypid())
            ->and($command->killPortProcesses($port))->toBeTrue()
            ->and($command->isPortInUse($port))->toBeFalse();
    } finally {
        fclose($client);
        $server->stop(0);
    }
});

test('lan:stop stops an alsenform server that has no background metadata', function () {
    $port = 59141;
    $server = startLanTestServer($port, [base_path('server.php')]);

    try {
        $command = lanCommandWithoutMetadata();
        $command->setInput(new ArrayInput(['--port' => (string) $port], $command->getDefinition()));
        $buffer = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

        expect($command->stop())->toBe(0)
            ->and($buffer->fetch())->toContain("Server Falcon Forms pada port {$port} telah dihentikan")
            ->and($command->isPortInUse($port))->toBeFalse();
    } finally {
        $server->stop(0);
    }
});

test('lan:stop --port stops the server on that port, not the background server next to it', function () {
    $backgroundPort = 59143;
    $oldPort = 59144;
    $background = startLanTestServer($backgroundPort, [base_path('server.php')]);
    $old = startLanTestServer($oldPort, [base_path('server.php')]);
    $infoFile = storage_path('framework/testing/lan_server_background.json');
    File::ensureDirectoryExists(dirname($infoFile));
    file_put_contents($infoFile, json_encode(['pid' => $background->getPid(), 'port' => $backgroundPort]));

    try {
        $command = new class($infoFile) extends ServeLanCommand
        {
            public function __construct(protected string $infoFile)
            {
                parent::__construct();
            }

            protected function infoFilePath(): string
            {
                return $this->infoFile;
            }

            public function stop(): int
            {
                return $this->handleStop();
            }
        };
        $command->setInput(new ArrayInput(['--port' => (string) $oldPort], $command->getDefinition()));
        $buffer = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

        expect($command->stop())->toBe(0)
            ->and($buffer->fetch())->toContain("Server Falcon Forms pada port {$oldPort} telah dihentikan")
            ->and($command->isPortInUse($oldPort))->toBeFalse()
            ->and($command->isPortInUse($backgroundPort))->toBeTrue()
            ->and(file_exists($infoFile))->toBeTrue();
    } finally {
        @unlink($infoFile);
        $background->stop(0);
        $old->stop(0);
    }
});

test('lan:stop leaves a server of another application running', function () {
    $port = 59142;
    $server = startLanTestServer($port, ['-t', public_path()]);

    try {
        $command = lanCommandWithoutMetadata();
        $command->setInput(new ArrayInput(['--port' => (string) $port], $command->getDefinition()));
        $buffer = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

        expect($command->stop())->toBe(1)
            ->and($buffer->fetch())->toContain('bukan oleh server Falcon Forms')
            ->and($command->isPortInUse($port))->toBeTrue();
    } finally {
        $server->stop(0);
    }
});

test('lan:serve explains a port it cannot free and offers the next free port', function () {
    $command = new class extends ServeLanCommand
    {
        /** @var list<list<string>> */
        public array $pidSnapshots = [['1327', '1541'], ['2001', '2002']];

        public function getPortPids(int $port): array
        {
            return array_shift($this->pidSnapshots) ?? ['2001', '2002'];
        }

        public function killPortProcesses(int $port): bool
        {
            return false;
        }

        public function findAvailablePort(int $startPort, int $maxTries = 10): int
        {
            return $startPort;
        }

        protected function describeProcesses(array $pids): array
        {
            return array_map(fn (string $pid): string => "{$pid} 1 root php -S 0.0.0.0:8000 server.php", $pids);
        }

        public function resolve(int $port): ?int
        {
            return $this->resolveBusyPort($port, '0.0.0.0');
        }
    };

    $input = new ArrayInput([], $command->getDefinition());
    $answers = fopen('php://memory', 'r+');
    fwrite($answers, "kill\nyes\n");
    rewind($answers);
    $input->setStream($answers);

    $buffer = new BufferedOutput;
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, $buffer));

    expect($command->resolve(8000))->toBe(8001);

    expect($buffer->fetch())->toContain('Gagal membebaskan port 8000')
        ->toContain('2001 1 root php -S 0.0.0.0:8000 server.php')
        ->toContain('Proses baru muncul lagi')
        ->toContain('sudo kill -9 $(sudo lsof -nP -iTCP:8000 -sTCP:LISTEN -t)')
        ->toContain('Menggunakan port baru yang tersedia: 8001');
});

test('lan:serve announces the new student url when the server ip address changes', function () {
    $command = new class extends ServeLanCommand
    {
        protected float $networkCheckIntervalSeconds = 0.0;

        /** @var list<array<string, string>> */
        public array $networkSnapshots = [
            ['Wi-Fi (en0)' => '192.168.1.20'],
            ['Wi-Fi (en0)' => '192.168.1.20'],
            ['Wi-Fi (en0)' => '10.10.5.33'],
        ];

        protected function detectNetworkInterfaces(): array
        {
            return array_shift($this->networkSnapshots) ?? ['Wi-Fi (en0)' => '10.10.5.33'];
        }

        public function watch(Process $process): void
        {
            $this->watchNetworkChanges($process, ['Wi-Fi (en0)' => '192.168.1.20'], '0.0.0.0', 8000);
        }
    };

    $buffer = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

    $process = Mockery::mock(Process::class);
    $process->shouldReceive('isRunning')->andReturn(true, true, true, false);

    $command->watch($process);

    $output = $buffer->fetch();

    expect($output)->toContain('ALAMAT IP SERVER BERUBAH')
        ->toContain('http://10.10.5.33:8000')
        ->not->toContain('http://192.168.1.20:8000')
        ->and(substr_count($output, 'ALAMAT IP SERVER BERUBAH'))->toBe(1);
});

test('lan:serve stops with a clear message when postgresql is not reachable', function () {
    $defaultConnection = config('database.default');
    config([
        'database.connections.unreachable' => array_merge(config("database.connections.{$defaultConnection}"), ['host' => '127.0.0.1', 'port' => 1]),
        'database.default' => 'unreachable',
    ]);

    try {
        $this->artisan('lan:serve --port=59124 --no-caffeinate')
            ->expectsOutputToContain('tidak dapat dihubungi')
            ->assertFailed();
    } finally {
        config(['database.default' => $defaultConnection]);
    }
});

test('lan:serve raises the php upload limits for examview zip packages and videos', function () {
    $command = new class extends ServeLanCommand
    {
        public function options(): array
        {
            return $this->phpRuntimeOptions();
        }
    };

    expect($command->options())->toContain('upload_max_filesize=64M')
        ->toContain('post_max_size=80M');
});

test('lan:serve bound to a vanished address only offers new urls after a restart', function () {
    $command = new class extends ServeLanCommand
    {
        public function announce(array $interfaces, string $host): void
        {
            $this->announceNetworkChange($interfaces, $host, 8000);
        }
    };

    $buffer = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));
    $command->announce(['Wi-Fi (en0)' => '10.10.5.33'], '192.168.1.20');

    $output = $buffer->fetch();

    expect($output)->toContain('sudah tidak tersedia')
        ->toContain('setelah server dijalankan ulang')
        ->not->toContain('Bagikan URL baru');
});

test('lan:status reports an ip change of the background server', function () {
    $infoFile = tempnam(sys_get_temp_dir(), 'lan_server_').'.json';
    file_put_contents($infoFile, json_encode([
        'pid' => getmypid(),
        'port' => 59125,
        'host' => '0.0.0.0',
        'workers' => 4,
        'started_at' => time() - 60,
        'addresses' => ['10.9.9.9'],
    ]));

    $command = new class($infoFile) extends ServeLanCommand
    {
        public function __construct(protected string $statusFile)
        {
            parent::__construct();
        }

        protected function infoFilePath(): string
        {
            return $this->statusFile;
        }

        protected function detectNetworkInterfaces(): array
        {
            return ['Wi-Fi (en0)' => '10.10.5.33'];
        }

        public function status(): int
        {
            return $this->handleStatus();
        }
    };

    $buffer = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

    expect($command->status())->toBe(0);

    $output = $buffer->fetch();
    expect($output)->toContain('ALAMAT IP SERVER BERUBAH')
        ->toContain('http://10.10.5.33:59125')
        ->and(json_decode((string) file_get_contents($infoFile), true)['addresses'])->toBe(['10.10.5.33']);

    @unlink($infoFile);
});

test('lan:serve explains a database connection that is not configured', function () {
    $defaultConnection = config('database.default');
    config(['database.default' => 'tidak_ada']);

    try {
        $this->artisan('lan:serve --port=59126 --no-caffeinate')
            ->expectsOutputToContain('tidak dapat dihubungi')
            ->assertFailed();
    } finally {
        config(['database.default' => $defaultConnection]);
    }
});

test('pending migrations after an update are detected', function () {
    expect(app(PendingMigrations::class)->names())->toBe([]);

    DB::table('migrations')->where('migration', '2026_10_04_140547_make_started_at_nullable_on_quiz_sessions_table')->delete();

    expect(app(PendingMigrations::class)->names())->toBe(['2026_10_04_140547_make_started_at_nullable_on_quiz_sessions_table']);
});

test('lan:serve brings the database and caches up to date before serving', function () {
    $command = new class extends ServeLanCommand
    {
        /** @var list<string> */
        public array $calls = [];

        public function call($command, array $arguments = []): int
        {
            $this->calls[] = $command;

            return 0;
        }

        public function sync(): void
        {
            $this->syncWithCurrentCode();
        }
    };
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    $command->sync();
    expect($command->calls)->toBe([]);

    DB::table('migrations')->where('migration', '2026_10_04_140547_make_started_at_nullable_on_quiz_sessions_table')->delete();
    $command->sync();
    expect($command->calls)->toBe(['migrate']);
});

test('lan:serve builds the pages again when the code is newer than the built bundle', function () {
    $directory = storage_path('framework/testing/assets-'.uniqid());
    mkdir("{$directory}/build", 0777, true);
    mkdir("{$directory}/js/pages", 0777, true);
    file_put_contents("{$directory}/js/pages/FormEditor.vue", '<template />');
    file_put_contents("{$directory}/build/manifest.json", '{}');
    touch("{$directory}/js/pages/FormEditor.vue", time() - 120);
    touch("{$directory}/build/manifest.json", time() - 60);

    $command = new class($directory) extends ServeLanCommand
    {
        public function __construct(public string $directory)
        {
            parent::__construct();
        }

        protected function manifestPath(): string
        {
            return "{$this->directory}/build/manifest.json";
        }

        protected function assetSourcePaths(): array
        {
            return ["{$this->directory}/js", "{$this->directory}/package.json"];
        }

        public function outdated(): bool
        {
            return $this->assetsAreOutdated();
        }
    };

    try {
        expect($command->outdated())->toBeFalse();

        // A merge brings a changed page: the bundle no longer matches the code.
        touch("{$directory}/js/pages/FormEditor.vue", time());
        expect($command->outdated())->toBeTrue();

        // Built again.
        touch("{$directory}/build/manifest.json", time() + 1);
        expect($command->outdated())->toBeFalse();

        unlink("{$directory}/build/manifest.json");
        expect($command->outdated())->toBeTrue();
    } finally {
        File::deleteDirectory($directory);
    }
});

/**
 * A lan:serve whose network's DNS answers as given, on a server with one Wi-Fi address.
 *
 * @param  array<string, string>  $dns
 */
function lanServeWithDns(array $dns): ServeLanCommand
{
    $command = new class($dns) extends ServeLanCommand
    {
        public function __construct(protected array $dns)
        {
            parent::__construct();
        }

        protected function resolveHost(string $host): ?string
        {
            return $this->dns[$host] ?? null;
        }

        protected function networkWarnings(array $interfaces): array
        {
            return [];
        }

        public function banner(): void
        {
            $this->outputBanner(['Wi-Fi (en0)' => '192.168.1.20'], self::DEFAULT_PORT, 24, false);
        }

        public function appUrl(string $host = '0.0.0.0'): ?string
        {
            return $this->applicationUrl(['Wi-Fi (en0)' => '192.168.1.20'], $host, self::DEFAULT_PORT);
        }

        public function announce(array $interfaces): void
        {
            $this->announceNetworkChange($interfaces, '0.0.0.0', self::DEFAULT_PORT);
        }
    };
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    return $command;
}

function lanOutput(ServeLanCommand $command): string
{
    return $command->getOutput()->getOutput()->fetch();
}

test('the lan commands serve on port 2026 unless told otherwise', function () {
    expect(ServeLanCommand::DEFAULT_PORT)->toBe(2026);

    foreach (['lan:serve', 'lan:status', 'lan:stop'] as $name) {
        expect(Artisan::all()[$name]->getDefinition()->getOption('port')->getDefault())->toBe('2026');
    }
});

test('falcon.lan is the address students use once the router points it at the server', function () {
    $command = lanServeWithDns(['falcon.lan' => '192.168.1.20']);
    $command->banner();

    expect(lanOutput($command))
        ->toContain('http://falcon.lan:2026')
        ->toContain('alamat intranet → 192.168.1.20')
        ->toContain('http://192.168.1.20:2026')
        ->toContain('http://falcon.lan:2026/cek-koneksi')
        ->not->toContain('belum aktif')
        ->not->toContain('alsenform.test')
        ->not->toContain('.local')
        ->not->toContain(':8000')
        ->and($command->appUrl())->toBe('http://falcon.lan:2026')
        ->and($command->appUrl('192.168.1.20'))->toBe('http://falcon.lan:2026');
});

test('lan:serve says which DNS entry the router needs while falcon.lan does not lead to the server', function () {
    $unknown = lanServeWithDns([]);
    $unknown->banner();

    expect(lanOutput($unknown))
        ->toContain('http://falcon.lan:2026')
        ->toContain('belum aktif')
        ->toContain('Router belum mengenal falcon.lan. Tambahkan DNS statis di router: falcon.lan → 192.168.1.20')
        ->toContain('DHCP Reservation')
        ->toContain('http://192.168.1.20:2026/cek-koneksi')
        ->and($unknown->appUrl())->toBe('http://192.168.1.20:2026');

    // The server got another address: the old DNS entry leads elsewhere.
    $stale = lanServeWithDns(['falcon.lan' => '192.168.1.50']);
    $stale->banner();

    expect(lanOutput($stale))
        ->toContain('falcon.lan masih mengarah ke 192.168.1.50, bukan ke server ini. Ubah DNS statisnya di router: falcon.lan → 192.168.1.20')
        ->and($stale->appUrl())->toBe('http://192.168.1.20:2026');
});

test('after an ip change lan:serve tells to point falcon.lan at the new address', function () {
    $command = lanServeWithDns(['falcon.lan' => '192.168.1.20']);
    $command->announce(['Wi-Fi (en0)' => '10.10.5.33']);

    expect(lanOutput($command))
        ->toContain('ALAMAT IP SERVER BERUBAH')
        ->toContain('Ubah DNS statisnya di router: falcon.lan → 10.10.5.33')
        ->toContain('http://10.10.5.33:2026');
});

test('another intranet name can be set with LAN_HOST, or none', function () {
    config(['app.lan_host' => 'ujian.sekolah.lan']);
    $command = lanServeWithDns(['ujian.sekolah.lan' => '192.168.1.20']);
    $command->banner();
    expect(lanOutput($command))->toContain('http://ujian.sekolah.lan:2026')->not->toContain('falcon.lan');

    config(['app.lan_host' => '']);
    $none = lanServeWithDns([]);
    $none->banner();
    expect(lanOutput($none))->not->toContain('alamat intranet')->toContain('http://192.168.1.20:2026')
        ->and($none->appUrl())->toBe('http://192.168.1.20:2026');
});

test('lan:serve warns about session cookie settings that stop logins over http://falcon.lan', function () {
    config(['session.domain' => 'alsenform.local', 'session.secure' => true]);
    $command = lanServeWithDns(['falcon.lan' => '192.168.1.20']);
    $command->banner();

    expect(lanOutput($command))
        ->toContain('SESSION_DOMAIN=alsenform.local di .env')
        ->toContain('SESSION_SECURE_COOKIE=true di .env');

    config(['session.domain' => null, 'session.secure' => null]);
    $fine = lanServeWithDns(['falcon.lan' => '192.168.1.20']);
    $fine->banner();

    expect(lanOutput($fine))->not->toContain('SESSION_');
});

/**
 * A lan:serve that finds a server on the old port 8000: one of its own, or another program.
 */
function lanServeWithLegacyServer(bool $isFalconServer, bool $interactive, string $answer = 'yes'): ServeLanCommand
{
    $command = new class($isFalconServer) extends ServeLanCommand
    {
        /** @var list<int> */
        public array $stoppedPorts = [];

        public function __construct(protected bool $isFalconServer)
        {
            parent::__construct();
        }

        public function isPortInUse(int $port, string $host = '127.0.0.1'): bool
        {
            return $port === self::LEGACY_PORT && ! in_array($port, $this->stoppedPorts, true);
        }

        public function getPortPids(int $port): array
        {
            return ['3001'];
        }

        protected function isAlsenformServer(array $pids): bool
        {
            return $this->isFalconServer;
        }

        public function killPortProcesses(int $port): bool
        {
            $this->stoppedPorts[] = $port;

            return true;
        }

        public function retire(int $port = self::DEFAULT_PORT): void
        {
            $this->retireLegacyServer($port);
        }
    };

    $input = new ArrayInput([], $command->getDefinition());
    $input->setInteractive($interactive);
    $answers = fopen('php://memory', 'r+');
    fwrite($answers, "{$answer}\n");
    rewind($answers);
    $input->setStream($answers);

    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, new BufferedOutput));

    return $command;
}

test('lan:serve stops its own server left on the old port 8000 when the operator agrees', function () {
    $command = lanServeWithLegacyServer(isFalconServer: true, interactive: true);
    $command->retire();

    expect($command->stoppedPorts)->toBe([8000])
        ->and(lanOutput($command))
        ->toContain('Server Falcon Forms lama masih berjalan di port 8000')
        ->toContain('Server lama di port 8000 dihentikan');
});

test('lan:serve leaves the old server running when declined or unattended, with the command to stop it', function (bool $interactive) {
    $command = lanServeWithLegacyServer(isFalconServer: true, interactive: $interactive, answer: 'no');
    $command->retire();

    expect($command->stoppedPorts)->toBe([])
        ->and(lanOutput($command))->toContain('php artisan lan:stop --port=8000');
})->with(['declined' => true, 'unattended' => false]);

test('lan:serve never touches another program on port 8000, nor a server asked to run there', function () {
    $other = lanServeWithLegacyServer(isFalconServer: false, interactive: true);
    $other->retire();

    $onLegacyPort = lanServeWithLegacyServer(isFalconServer: true, interactive: true);
    $onLegacyPort->retire(8000);

    expect($other->stoppedPorts)->toBe([])
        ->and(lanOutput($other))->toBe('')
        ->and($onLegacyPort->stoppedPorts)->toBe([])
        ->and(lanOutput($onLegacyPort))->toBe('');
});
