<?php

use App\Console\Commands\ServeLanCommand;
use App\Support\DnsLookup;
use Carbon\CarbonImmutable;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

/*
 * lan:check: why http://falcon.intranet:2026 does not open yet, checked on the server and on the
 * school network the way a student's device sees it, with the fix for each problem.
 */

/**
 * A DNS server like a school router's: it knows falcon.intranet, refuses refused.lan, and nothing else.
 */
function startFakeRouterDns(int $port): Process
{
    $script = <<<'PHP'
        $socket = stream_socket_server('udp://127.0.0.1:'.$argv[1], $code, $message, STREAM_SERVER_BIND);
        while (true) {
            $query = stream_socket_recvfrom($socket, 512, 0, $peer);
            $offset = 12;
            $labels = [];
            while (($length = ord($query[$offset])) !== 0) {
                $labels[] = substr($query, $offset + 1, $length);
                $offset += $length + 1;
            }
            $question = substr($query, 12, $offset + 5 - 12);
            $name = implode('.', $labels);
            $response = match ($name) {
                'falcon.intranet' => substr($query, 0, 2).pack('nnnnn', 0x8180, 1, 1, 0, 0).$question."\xC0\x0C".pack('nnNn', 1, 1, 60, 4).inet_pton('192.168.1.20'),
                'refused.lan' => substr($query, 0, 2).pack('nnnnn', 0x8185, 1, 0, 0, 0).$question,
                default => substr($query, 0, 2).pack('nnnnn', 0x8183, 1, 0, 0, 0).$question,
            };
            stream_socket_sendto($socket, $response, 0, $peer);
        }
        PHP;

    $process = new Process([PHP_BINARY, '-r', $script, (string) $port]);
    $process->start();

    $deadline = microtime(true) + 5;
    while (microtime(true) < $deadline && (new DnsLookup)->query('127.0.0.1', 'falcon.intranet', $port, 0.2)['status'] !== DnsLookup::FOUND) {
        usleep(50 * 1000);
    }

    return $process;
}

test('the DNS lookup asks a router directly, and tells unknown, refused and silent apart', function () {
    $router = startFakeRouterDns(59153);

    try {
        $lookup = new DnsLookup;

        expect($lookup->query('127.0.0.1', 'falcon.intranet', 59153))->toBe(['status' => DnsLookup::FOUND, 'addresses' => ['192.168.1.20']])
            ->and($lookup->query('127.0.0.1', 'falcon.intranet.', 59153)['addresses'])->toBe(['192.168.1.20'])
            ->and($lookup->query('127.0.0.1', 'tidak-ada.lan', 59153)['status'])->toBe(DnsLookup::UNKNOWN)
            ->and($lookup->query('127.0.0.1', 'refused.lan', 59153)['status'])->toBe(DnsLookup::REFUSED)
            ->and($lookup->query('127.0.0.1', 'falcon.intranet', 59154, 0.5)['status'])->toBe(DnsLookup::NO_ANSWER);
    } finally {
        $router->stop(0);
    }
});

/**
 * Run lan:check on a server and network described by the probes.
 *
 * @param  array<string, mixed>  $probes
 * @return array{0: int, 1: string}
 */
function runLanCheck(array $probes = []): array
{
    $command = new class($probes) extends ServeLanCommand
    {
        public function __construct(protected array $probes)
        {
            parent::__construct();
        }

        protected function detectNetworkInterfaces(): array
        {
            return $this->probes['interfaces'] ?? ['Wi-Fi (en0)' => '192.168.1.20'];
        }

        protected function defaultGateway(): ?string
        {
            return array_key_exists('gateway', $this->probes) ? $this->probes['gateway'] : '192.168.1.1';
        }

        protected function dnsServers(): array
        {
            return $this->probes['dnsServers'] ?? ['192.168.1.1'];
        }

        protected function lookupAt(string $server, string $name): array
        {
            [$status, $addresses] = $this->probes['dns'][$server] ?? [DnsLookup::FOUND, ['192.168.1.20']];

            return ['status' => $status, 'addresses' => $addresses];
        }

        protected function routerPage(string $gateway): ?string
        {
            return $this->probes['routerPage'] ?? null;
        }

        protected function hasWinbox(string $gateway): bool
        {
            return $this->probes['winbox'] ?? false;
        }

        protected function describeProcesses(array $pids): array
        {
            return $this->probes['processes'] ?? [];
        }

        protected function resolveHost(string $host): ?string
        {
            return array_key_exists('serverDns', $this->probes) ? $this->probes['serverDns'] : '192.168.1.20';
        }

        public function isPortInUse(int $port, string $host = '127.0.0.1'): bool
        {
            return in_array($port, $this->probes['listening'] ?? [2026], true);
        }

        public function getPortPids(int $port): array
        {
            return [(string) $port];
        }

        protected function isAlsenformServer(array $pids): bool
        {
            return ! in_array((int) $pids[0], $this->probes['otherPrograms'] ?? [], true);
        }

        protected function httpStatus(string $url): ?int
        {
            return $this->probes['http'] ?? 200;
        }

        protected function firewallProblems(): array
        {
            return $this->probes['firewall'] ?? [];
        }

        protected function viteHotUrl(): ?string
        {
            return $this->probes['hot'] ?? null;
        }

        protected function isListening(string $url): bool
        {
            return $this->probes['hotRunning'] ?? false;
        }

        protected function manifestPath(): string
        {
            return __FILE__;
        }

        protected function assetsAreOutdated(): bool
        {
            return $this->probes['outdated'] ?? false;
        }

        protected function proxyStatus(string $lanHost, string $address, int $publicPort): ?int
        {
            return array_key_exists('proxy', $this->probes) ? $this->probes['proxy'] : 200;
        }

        protected function answersAt(string $address, int $port): bool
        {
            return in_array("{$address}:{$port}", $this->probes['answering'] ?? ["192.168.1.20:{$port}"], true);
        }

        protected function latestBackup(): ?array
        {
            return array_key_exists('backup', $this->probes)
                ? $this->probes['backup']
                : ['path' => '/tmp/falcon-forms-20261028-010000.dump', 'name' => 'falcon-forms-20261028-010000.dump', 'size' => 1024, 'created_at' => CarbonImmutable::now()->subHours(3)];
        }

        public function check(): int
        {
            return $this->handleCheck();
        }
    };

    $input = new ArrayInput(['--port' => '2026'], $command->getDefinition());
    $output = new BufferedOutput;
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, $output));

    return [$command->check(), $output->fetch()];
}

beforeEach(function () {
    config(['app.lan_host' => 'falcon.intranet', 'app.url' => 'http://falcon.intranet:2026', 'session.domain' => null, 'session.secure' => null]);
});

test('lan:check is available and passes when the server and the router are ready', function () {
    expect(Artisan::all())->toHaveKey('lan:check');

    [$exitCode, $output] = runLanCheck();

    expect($exitCode)->toBe(0)
        ->and($output)
        ->toContain('PEMERIKSAAN ALAMAT INTRANET http://falcon.intranet:2026')
        ->toContain('✓ Tampilan sudah di-build')
        ->toContain('✓ Server Falcon Forms berjalan di port 2026')
        ->toContain('✓ Server menjawab di http://192.168.1.20:2026')
        ->toContain('✓ Router 192.168.1.1 mengenal falcon.intranet → 192.168.1.20')
        ->toContain('✓ http://falcon.intranet:2026 terbuka dari komputer server ini.')
        ->toContain('Matikan "DNS Pribadi"')
        ->toContain('Tidak ada masalah')
        ->not->toContain('✗');
});

test('lan:check names everything left from the old address and how to fix it', function () {
    config(['app.url' => 'http://alsenform.local:8000', 'session.domain' => 'alsenform.local']);

    [$exitCode, $output] = runLanCheck([
        'listening' => [8000],
        'hot' => 'http://192.168.1.20:5173',
        'outdated' => true,
        'dnsServers' => ['8.8.8.8'],
        'dns' => ['192.168.1.1' => [DnsLookup::UNKNOWN, []], '8.8.8.8' => [DnsLookup::UNKNOWN, []]],
        'serverDns' => null,
    ]);

    expect($exitCode)->toBe(1)
        ->and($output)
        ->toContain('✗ Tampilan lebih lama daripada kode')
        ->toContain('npm run build')
        ->toContain('✗ Berkas public/hot tertinggal dari npm run dev')
        ->toContain('⚠ APP_URL=http://alsenform.local:8000')
        ->toContain('APP_URL=http://falcon.intranet:2026')
        ->toContain('✗ SESSION_DOMAIN=alsenform.local')
        ->toContain('✗ Server belum berjalan di port 2026.')
        ->toContain('→ Jalankan: php artisan lan:serve')
        ->toContain('✗ Server lama masih berjalan di port 8000')
        ->toContain('php artisan lan:stop --port=8000')
        ->toContain('✗ Router 192.168.1.1 belum mengenal falcon.intranet')
        ->toContain('Tambahkan DNS statis di router: falcon.intranet → 192.168.1.20')
        ->toContain('✗ DNS yang dipakai komputer ini (8.8.8.8) tidak mengenal falcon.intranet')
        ->toContain('Modem bawaan penyedia internet')
        ->toContain('Pasang DNS statis falcon.intranet → 192.168.1.20 di router, lalu di pengaturan DHCP router isi DNS server dengan IP router itu.')
        ->toContain('⚠ Komputer server juga belum mengenal falcon.intranet, karena router belum mengenalnya.')
        ->toContain('7 masalah perlu diperbaiki');
});

test('lan:check counts a router that does not know the name once, and gives the menus of that router', function () {
    // The situation of a school on MikroTik: the old server still on 8000, the router answering DNS
    // for the whole network but not knowing falcon.intranet yet.
    [$exitCode, $output] = runLanCheck([
        'listening' => [8000],
        'gateway' => '172.16.0.1',
        'interfaces' => ['Wi-Fi (en1)' => '172.16.2.49'],
        'dnsServers' => ['172.16.0.1'],
        'dns' => ['172.16.0.1' => [DnsLookup::UNKNOWN, []]],
        'serverDns' => null,
        'routerPage' => '<html><head><title>RouterOS router configuration page</title></head></html>',
        'processes' => ['4120 1 guru /usr/bin/php -S 0.0.0.0:8000 /Users/guru/alsenform/server.php'],
    ]);

    expect($exitCode)->toBe(1)
        ->and($output)
        ->toContain('✗ Router 172.16.0.1 (MikroTik) belum mengenal falcon.intranet')
        ->toContain('IP → DNS → Static → tombol + → Name: falcon.intranet, Address: 172.16.2.49')
        ->toContain('/ip dns static add name=falcon.intranet address=172.16.2.49')
        ->toContain('/ip dhcp-server lease make-static [find address=172.16.2.49]')
        ->toContain('✓ Perangkat mendapat DNS dari router (172.16.0.1): begitu router mengenal falcon.intranet, perangkat siswa ikut mengenalnya.')
        ->toContain('⚠ Komputer server juga belum mengenal falcon.intranet, karena router belum mengenalnya.')
        ->not->toContain('DNS-nya bukan router')
        ->not->toContain('DNS yang dipakai komputer ini')
        ->toContain('Prosesnya (PID, PID induk, pengguna, perintah): 4120 1 guru /usr/bin/php -S 0.0.0.0:8000')
        ->toContain('sudo kill -9 $(sudo lsof -nP -iTCP:8000 -sTCP:LISTEN -t)')
        ->toContain('3 masalah perlu diperbaiki');

    // A MikroTik whose web page is switched off still has Winbox open.
    [, $winbox] = runLanCheck(['dns' => ['192.168.1.1' => [DnsLookup::UNKNOWN, []]], 'winbox' => true]);
    expect($winbox)->toContain('Router 192.168.1.1 (MikroTik) belum mengenal falcon.intranet')
        ->toContain('/ip dns static add name=falcon.intranet address=192.168.1.20');

    $routerWith = fn (string $page): string => runLanCheck(['dns' => ['192.168.1.1' => [DnsLookup::UNKNOWN, []]], 'routerPage' => $page])[1];

    expect($routerWith('<title>OpenWrt - LuCI</title>'))->toContain('Router 192.168.1.1 (OpenWrt)')->toContain('Network → DHCP and DNS → Hostnames → Add: Hostname falcon.intranet, IP 192.168.1.20')
        ->and($routerWith('<title>pfSense - Login</title>'))->toContain('Host Overrides → Add: Host falcon, Domain intranet, IP 192.168.1.20')
        ->and($routerWith('<title>F670L</title><script>ZTE</script>'))->toContain('Router 192.168.1.1 (ZTE)')->toContain('modem penyedia internet (ZTE)')
        ->and($routerWith('<title>Router</title>'))->toContain('Router 192.168.1.1 belum mengenal')->toContain('Tambahkan DNS statis di router: falcon.intranet → 192.168.1.20');
});

test('lan:check tells the server to forget an old DNS answer once the router knows the name', function () {
    [, $output] = runLanCheck(['serverDns' => null]);

    expect($output)->toContain('✓ Router 192.168.1.1 mengenal falcon.intranet')
        ->toContain('⚠ Router sudah mengenal falcon.intranet, tetapi komputer server masih mengingat jawaban lama.')
        ->toContain('Kosongkan cache DNS');
});

test('lan:check explains a router that refuses, points elsewhere, or does not answer DNS', function () {
    [, $refused] = runLanCheck(['dns' => ['192.168.1.1' => [DnsLookup::REFUSED, []]]]);
    expect($refused)->toContain('✗ Router 192.168.1.1 menolak menjawab DNS')->toContain('Allow Remote Requests');

    [, $stale] = runLanCheck(['dns' => ['192.168.1.1' => [DnsLookup::FOUND, ['192.168.1.50']]]]);
    expect($stale)->toContain('✗ Router 192.168.1.1 mengarahkan falcon.intranet ke 192.168.1.50, bukan ke server ini (192.168.1.20)')
        ->toContain('Ubah DNS statis di router: falcon.intranet → 192.168.1.20');

    [, $dhcp] = runLanCheck(['dnsServers' => ['8.8.8.8'], 'dns' => ['8.8.8.8' => [DnsLookup::UNKNOWN, []]]]);
    expect($dhcp)->toContain('✓ Router 192.168.1.1 mengenal falcon.intranet')
        ->toContain('Di pengaturan DHCP router, isi DNS server dengan 192.168.1.1, bukan 8.8.8.8');

    [, $silent] = runLanCheck(['dns' => ['192.168.1.1' => [DnsLookup::NO_ANSWER, []], '10.0.0.53' => [DnsLookup::FOUND, ['192.168.1.20']]], 'dnsServers' => ['10.0.0.53']]);
    expect($silent)->toContain('⚠ Router 192.168.1.1 tidak menjawab DNS')
        ->toContain('✓ DNS yang dibagikan jaringan (10.0.0.53) mengenal falcon.intranet');
});

test('lan:check reports a blocking firewall, a busy port and a server that does not answer', function () {
    [$exitCode, $output] = runLanCheck([
        'firewall' => [['Firewall macOS memblokir semua koneksi masuk: perangkat siswa tidak dapat terhubung.', 'matikan "Block all incoming connections".']],
        'http' => 500,
    ]);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('✗ Firewall macOS memblokir semua koneksi masuk')
        ->toContain('Block all incoming connections')
        ->toContain('✗ Server tidak menjawab dengan benar di http://192.168.1.20:2026 (HTTP 500)');

    [, $busy] = runLanCheck(['otherPrograms' => [2026]]);
    expect($busy)->toContain('✗ Port 2026 dipakai program lain, bukan Falcon Forms.');
});

test('lan:serve removes public/hot left by npm run dev, but not while the dev server runs', function () {
    $hotFile = storage_path('framework/testing/hot');
    @mkdir(dirname($hotFile), 0755, true);
    $listener = stream_socket_server('tcp://127.0.0.1:0');
    $runningUrl = 'http://'.stream_socket_get_name($listener, false);

    $command = new class($hotFile) extends ServeLanCommand
    {
        public function __construct(protected string $hotFile)
        {
            parent::__construct();
        }

        protected function viteHotFile(): string
        {
            return $this->hotFile;
        }

        public function clean(): void
        {
            $this->removeStaleViteHotFile();
        }
    };
    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));

    try {
        file_put_contents($hotFile, 'http://127.0.0.1:59155');
        $command->clean();
        expect(file_exists($hotFile))->toBeFalse()
            ->and($output->fetch())->toContain('public/hot (sisa npm run dev) dihapus');

        file_put_contents($hotFile, $runningUrl);
        $command->clean();
        expect(file_exists($hotFile))->toBeTrue()
            ->and($output->fetch())->toContain('npm run dev masih berjalan');
    } finally {
        fclose($listener);
        @unlink($hotFile);
    }
});

test('lan:check counts a missing or stale database backup as a problem', function () {
    [, $fresh] = runLanCheck();
    expect($fresh)->toContain('✓ Backup database terakhir 3 jam yang lalu (falcon-forms-20261028-010000.dump).');

    [$exitCode, $none] = runLanCheck(['backup' => null]);
    expect($exitCode)->toBe(1)
        ->and($none)->toContain('✗ Belum ada backup database')
        ->toContain('Jalankan: php artisan db:backup');

    [, $stale] = runLanCheck(['backup' => ['path' => '/tmp/a.dump', 'name' => 'falcon-forms-20261020-010000.dump', 'size' => 1024, 'created_at' => CarbonImmutable::now()->subDays(2)]]);
    expect($stale)->toContain('✗ Backup database terakhir 2 hari yang lalu (falcon-forms-20261020-010000.dump), lebih dari 24 jam.');
});

test('behind Caddy, lan:check expects the address without a port and checks that Caddy leads it here', function () {
    config(['app.lan_public_port' => 80, 'app.url' => 'http://falcon.intranet']);

    [$exitCode, $output] = runLanCheck(['listening' => [2026, 80]]);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('PEMERIKSAAN ALAMAT INTRANET http://falcon.intranet')
        ->toContain('✓ APP_URL=http://falcon.intranet')
        ->toContain('✓ Caddy meneruskan http://falcon.intranet ke server Falcon Forms (port 2026).')
        ->toContain('✓ http://falcon.intranet terbuka dari komputer server ini.')
        ->toContain('Ketik alamat lengkap: http://falcon.intranet (dengan http://)');

    [, $notRunning] = runLanCheck(['answering' => []]);
    expect($notRunning)->toContain('✗ Caddy belum menjawab di 192.168.1.20:80: http://falcon.intranet belum dapat dibuka.')
        ->toContain('php artisan lan:caddy --write=')
        ->not->toContain('default_bind');

    // A Mac with Herd: its nginx keeps port 80 on 127.0.0.1, so Caddy takes the network address only.
    [, $herd] = runLanCheck(['answering' => ['127.0.0.1:80']]);
    expect($herd)->toContain('✗ Caddy belum menjawab di 192.168.1.20:80')
        ->toContain('Port 80 di 127.0.0.1 dipakai program lain (di Mac biasanya nginx milik Herd)')
        ->toContain('{ default_bind 192.168.1.20 }');

    [, $noSite] = runLanCheck(['listening' => [2026, 80], 'proxy' => 0]);
    expect($noSite)->toContain('✗ Port 80 menjawab, tetapi http://falcon.intranet belum sampai ke Falcon Forms (halaman kosong): situs Caddy untuk falcon.intranet belum ada atau salah.');

    [, $serverOff] = runLanCheck(['listening' => [80], 'proxy' => 502]);
    expect($serverOff)->toContain('✗ Caddy berjalan, tetapi server Falcon Forms di port 2026 belum menjawab (HTTP 502).');

    // The old address with the port is not the one to put in .env any more.
    config(['app.url' => 'http://falcon.intranet:2026']);
    [, $oldUrl] = runLanCheck(['listening' => [2026, 80]]);
    expect($oldUrl)->toContain('Ubah di .env: APP_URL=http://falcon.intranet');
});
