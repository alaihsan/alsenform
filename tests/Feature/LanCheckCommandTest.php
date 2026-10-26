<?php

use App\Console\Commands\ServeLanCommand;
use App\Support\DnsLookup;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

/*
 * lan:check: why http://falcon.lan:2026 does not open yet, checked on the server and on the
 * school network the way a student's device sees it, with the fix for each problem.
 */

/**
 * A DNS server like a school router's: it knows falcon.lan, refuses refused.lan, and nothing else.
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
                'falcon.lan' => substr($query, 0, 2).pack('nnnnn', 0x8180, 1, 1, 0, 0).$question."\xC0\x0C".pack('nnNn', 1, 1, 60, 4).inet_pton('192.168.1.20'),
                'refused.lan' => substr($query, 0, 2).pack('nnnnn', 0x8185, 1, 0, 0, 0).$question,
                default => substr($query, 0, 2).pack('nnnnn', 0x8183, 1, 0, 0, 0).$question,
            };
            stream_socket_sendto($socket, $response, 0, $peer);
        }
        PHP;

    $process = new Process([PHP_BINARY, '-r', $script, (string) $port]);
    $process->start();

    $deadline = microtime(true) + 5;
    while (microtime(true) < $deadline && (new DnsLookup)->query('127.0.0.1', 'falcon.lan', $port, 0.2)['status'] !== DnsLookup::FOUND) {
        usleep(50 * 1000);
    }

    return $process;
}

test('the DNS lookup asks a router directly, and tells unknown, refused and silent apart', function () {
    $router = startFakeRouterDns(59153);

    try {
        $lookup = new DnsLookup;

        expect($lookup->query('127.0.0.1', 'falcon.lan', 59153))->toBe(['status' => DnsLookup::FOUND, 'addresses' => ['192.168.1.20']])
            ->and($lookup->query('127.0.0.1', 'falcon.lan.', 59153)['addresses'])->toBe(['192.168.1.20'])
            ->and($lookup->query('127.0.0.1', 'tidak-ada.lan', 59153)['status'])->toBe(DnsLookup::UNKNOWN)
            ->and($lookup->query('127.0.0.1', 'refused.lan', 59153)['status'])->toBe(DnsLookup::REFUSED)
            ->and($lookup->query('127.0.0.1', 'falcon.lan', 59154, 0.5)['status'])->toBe(DnsLookup::NO_ANSWER);
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
    config(['app.lan_host' => 'falcon.lan', 'app.url' => 'http://falcon.lan:2026', 'session.domain' => null, 'session.secure' => null]);
});

test('lan:check is available and passes when the server and the router are ready', function () {
    expect(Artisan::all())->toHaveKey('lan:check');

    [$exitCode, $output] = runLanCheck();

    expect($exitCode)->toBe(0)
        ->and($output)
        ->toContain('PEMERIKSAAN ALAMAT INTRANET http://falcon.lan:2026')
        ->toContain('✓ Tampilan sudah di-build')
        ->toContain('✓ Server Falcon Forms berjalan di port 2026')
        ->toContain('✓ Server menjawab di http://192.168.1.20:2026')
        ->toContain('✓ Router 192.168.1.1 mengenal falcon.lan → 192.168.1.20')
        ->toContain('✓ http://falcon.lan:2026 terbuka dari komputer server ini.')
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
        ->toContain('APP_URL=http://falcon.lan:2026')
        ->toContain('✗ SESSION_DOMAIN=alsenform.local')
        ->toContain('✗ Server belum berjalan di port 2026.')
        ->toContain('→ Jalankan: php artisan lan:serve')
        ->toContain('✗ Server lama masih berjalan di port 8000')
        ->toContain('php artisan lan:stop --port=8000')
        ->toContain('✗ Router 192.168.1.1 belum mengenal falcon.lan')
        ->toContain('Tambahkan DNS statis di router: falcon.lan → 192.168.1.20')
        ->toContain('✗ DNS yang dipakai komputer ini (8.8.8.8) tidak mengenal falcon.lan')
        ->toContain('Modem bawaan penyedia internet')
        ->toContain('Pasang DNS statis falcon.lan → 192.168.1.20 di router, lalu di pengaturan DHCP router isi DNS server dengan IP router itu.')
        ->toContain('⚠ Komputer server sendiri belum mengenal falcon.lan')
        ->toContain('7 masalah perlu diperbaiki');
});

test('lan:check explains a router that refuses, points elsewhere, or does not answer DNS', function () {
    [, $refused] = runLanCheck(['dns' => ['192.168.1.1' => [DnsLookup::REFUSED, []]]]);
    expect($refused)->toContain('✗ Router 192.168.1.1 menolak menjawab DNS')->toContain('Allow Remote Requests');

    [, $stale] = runLanCheck(['dns' => ['192.168.1.1' => [DnsLookup::FOUND, ['192.168.1.50']]]]);
    expect($stale)->toContain('✗ Router 192.168.1.1 mengarahkan falcon.lan ke 192.168.1.50, bukan ke server ini (192.168.1.20)')
        ->toContain('Ubah DNS statis di router: falcon.lan → 192.168.1.20');

    [, $dhcp] = runLanCheck(['dnsServers' => ['8.8.8.8'], 'dns' => ['8.8.8.8' => [DnsLookup::UNKNOWN, []]]]);
    expect($dhcp)->toContain('✓ Router 192.168.1.1 mengenal falcon.lan')
        ->toContain('Di pengaturan DHCP router, isi DNS server dengan 192.168.1.1, bukan 8.8.8.8');

    [, $silent] = runLanCheck(['dns' => ['192.168.1.1' => [DnsLookup::NO_ANSWER, []], '10.0.0.53' => [DnsLookup::FOUND, ['192.168.1.20']]], 'dnsServers' => ['10.0.0.53']]);
    expect($silent)->toContain('⚠ Router 192.168.1.1 tidak menjawab DNS')
        ->toContain('✓ DNS yang dibagikan jaringan (10.0.0.53) mengenal falcon.lan');
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
