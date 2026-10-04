<?php

use App\Console\Commands\ServeLanCommand;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

test('lan:serve command is registered and outputs description and force option', function () {
    $this->artisan('lan:serve --help')
        ->assertSuccessful()
        ->expectsOutputToContain('Jalankan aplikasi Alsenform di jaringan lokal (Wi-Fi 5GHz, 2.4GHz, dan Kabel LAN)')
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

        public function testHostName(): ?string
        {
            return $this->detectLocalHostName();
        }
    };

    $interfaces = $command->testInterfaces();
    expect($interfaces)->toBeArray();

    $hostname = $command->testHostName();
    expect($hostname === null || is_string($hostname))->toBeTrue();
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
