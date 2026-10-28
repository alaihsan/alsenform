<?php

use App\Console\Commands\CaddySiteCommand;
use App\Console\Commands\ServeLanCommand;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/*
 * Behind Caddy: several web apps share one server and one entry (port 80), each under its own
 * intranet name (http://falcon.intranet, http://perpus.intranet). lan:caddy writes the site of
 * Falcon Forms; lan:serve and lan:check know the address without a port.
 */

beforeEach(function () {
    config(['app.lan_host' => 'falcon.intranet', 'app.lan_public_port' => null]);
    $this->sites = storage_path('framework/testing/caddy-'.uniqid());
});

afterEach(fn () => File::deleteDirectory($this->sites));

test('lan:caddy writes the Caddy site that leads the intranet name to lan:serve', function () {
    expect(Artisan::call('lan:caddy'))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain("http://falcon.intranet {\n")
        ->toContain("\treverse_proxy 127.0.0.1:2026\n")
        ->toContain('max_size 80MB')
        ->not->toContain('tls internal')
        ->toContain('LAN_PUBLIC_PORT=80')
        ->toContain('APP_URL=http://falcon.intranet')
        ->toContain('/ip dns static add regexp=".*\\\\.intranet\\$"');

    Artisan::call('lan:caddy', ['--https' => true, '--port' => 2030]);
    expect(Artisan::output())->toContain("https://falcon.intranet {\n")
        ->toContain('tls internal')
        ->toContain('reverse_proxy 127.0.0.1:2030')
        ->toContain('LAN_PUBLIC_PORT=443');
});

test('lan:caddy saves the site in Caddy\'s folder of sites, and only replaces a site it wrote itself', function () {
    $site = "{$this->sites}/falcon-forms.caddy";

    expect(Artisan::call('lan:caddy', ['--write' => $site]))->toBe(0)
        ->and(File::get($site))->toContain(CaddySiteCommand::MARKER)->toContain('http://falcon.intranet {');

    // Written again (another port), it is replaced.
    Artisan::call('lan:caddy', ['--write' => $site, '--port' => 2027]);
    expect(File::get($site))->toContain('reverse_proxy 127.0.0.1:2027');

    // A site of another app is never overwritten.
    $other = "{$this->sites}/perpus.caddy";
    File::put($other, "http://perpus.intranet {\n\treverse_proxy 127.0.0.1:3000\n}\n");

    expect(Artisan::call('lan:caddy', ['--write' => $other]))->toBe(1)
        ->and(Artisan::output())->toContain('tidak ditimpa')
        ->and(File::get($other))->toContain('perpus.intranet');
});

/**
 * lan:caddy on a computer where another web server may keep the port on 127.0.0.1.
 */
function lanCaddyWith(?string $otherServer): string
{
    $command = new class($otherServer) extends CaddySiteCommand
    {
        public function __construct(protected ?string $otherServer)
        {
            parent::__construct();
        }

        protected function otherServerOnLoopback(int $port): ?string
        {
            return $this->otherServer;
        }

        protected function serverAddress(string $host): ?string
        {
            return '192.168.1.20';
        }
    };
    $command->setLaravel(app());
    $output = new BufferedOutput;
    $command->run(new ArrayInput([]), $output);

    return $output->fetch();
}

test('on a Mac with Herd, lan:caddy has Caddy take only the network address, beside Herd\'s nginx', function () {
    $herd = lanCaddyWith('nginx');

    expect($herd)->toContain("{\n")
        ->toContain('default_bind 192.168.1.20')
        ->toContain('/caddy/sites/*.caddy')
        ->toContain('Port 80 di 127.0.0.1 sudah dipakai nginx (di Mac biasanya Herd')
        ->toContain('DHCP Reservation');

    // Nothing else on the port: Caddy listens on every address, no default_bind.
    expect(lanCaddyWith(null))->not->toContain('default_bind')->toContain('/caddy/sites/*.caddy');
});

test('the main Caddyfile loads every app\'s site from one folder', function () {
    expect((new CaddySiteCommand)->mainCaddyfile('/opt/homebrew/etc/caddy/sites', '192.168.1.20'))->toBe([
        '{',
        "\tdefault_bind 192.168.1.20",
        '}',
        '',
        'import /opt/homebrew/etc/caddy/sites/*.caddy',
    ])->and((new CaddySiteCommand)->mainCaddyfile('/etc/caddy/sites', null))->toBe(['import /etc/caddy/sites/*.caddy']);
});

test('lan:caddy needs an intranet name', function () {
    config(['app.lan_host' => '']);

    expect(Artisan::call('lan:caddy'))->toBe(2)
        ->and(Artisan::output())->toContain('LAN_HOST');
});

/**
 * A lan:serve behind Caddy on the given port, whose network knows falcon.intranet.
 */
function lanServeBehindCaddy(int $publicPort, bool $isCaddyRunning): ServeLanCommand
{
    config(['app.lan_public_port' => $publicPort]);

    $command = new class($isCaddyRunning) extends ServeLanCommand
    {
        public function __construct(protected bool $isCaddyRunning)
        {
            parent::__construct();
        }

        protected function resolveHost(string $host): ?string
        {
            return $host === 'falcon.intranet' ? '192.168.1.20' : null;
        }

        protected function answersAt(string $address, int $port): bool
        {
            return $this->isCaddyRunning && $address === '192.168.1.20' && in_array($port, [80, 443], true);
        }

        protected function networkWarnings(array $interfaces): array
        {
            return [];
        }

        public function banner(): void
        {
            $this->outputBanner(['Wi-Fi (en0)' => '192.168.1.20'], self::DEFAULT_PORT, 24, false);
        }

        public function appUrl(): ?string
        {
            return $this->applicationUrl(['Wi-Fi (en0)' => '192.168.1.20'], '0.0.0.0', self::DEFAULT_PORT);
        }
    };
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    return $command;
}

test('behind Caddy, lan:serve gives students the intranet name without a port', function () {
    $command = lanServeBehindCaddy(80, isCaddyRunning: true);
    $command->banner();
    $output = $command->getOutput()->getOutput()->fetch();

    expect($output)->toContain('http://falcon.intranet (alamat intranet → 192.168.1.20, lewat Caddy)')
        ->toContain('http://falcon.intranet/cek-koneksi')
        // The address of the server itself still works, on lan:serve's own port.
        ->toContain('http://192.168.1.20:2026')
        ->not->toContain('falcon.intranet:2026')
        ->not->toContain('Caddy belum menjawab')
        ->and($command->appUrl())->toBe('http://falcon.intranet');

    expect(lanServeBehindCaddy(443, isCaddyRunning: true)->appUrl())->toBe('https://falcon.intranet');
});

test('lan:serve says when Caddy is not running in front of it', function () {
    $command = lanServeBehindCaddy(80, isCaddyRunning: false);
    $command->banner();

    expect($command->getOutput()->getOutput()->fetch())->toContain('Caddy belum menjawab di 192.168.1.20:80, jadi http://falcon.intranet belum terbuka')
        ->toContain('php artisan lan:check');
});
