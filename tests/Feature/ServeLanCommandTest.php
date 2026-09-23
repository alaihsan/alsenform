<?php

use App\Console\Commands\ServeLanCommand;

test('lan:serve command is registered and outputs description and force option', function () {
    $this->artisan('lan:serve --help')
        ->assertSuccessful()
        ->expectsOutputToContain('Jalankan aplikasi Alsenform di jaringan lokal (Wi-Fi 5GHz, 2.4GHz, dan Kabel LAN)')
        ->expectsOutputToContain('--force');
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
