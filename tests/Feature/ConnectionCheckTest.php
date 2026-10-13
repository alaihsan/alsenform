<?php

use App\Console\Commands\ServeLanCommand;
use App\Http\Controllers\ConnectionCheckController;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('anyone can open the connection check, also before logging in', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.23'])
        ->get(route('connection-check'), ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ConnectionCheck')
            ->where('client.ip', '192.168.1.23')
            ->where('client.viaProxy', false)
            ->where('client.device', 'PC Windows · Windows 10/11 · Chrome 129')
            ->where('pingUrl', route('connection-check.ping'))
            ->where('downloadUrl', route('connection-check.download')));
});

test('a request that came through a proxy is pointed out', function () {
    $this->get(route('connection-check'), ['Via' => '1.1 proxy.sekolah'])
        ->assertInertia(fn (Assert $page) => $page->where('client.viaProxy', true));
});

test('the ping is tiny, never cached, and tells the time the server took', function () {
    $response = $this->get(route('connection-check.ping'))->assertOk()->assertExactJson(['ok' => true]);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Server-Timing'))->toMatch('/^app;dur=\d+(\.\d+)?$/');
});

test('the test download is a megabyte of data that cannot be compressed', function () {
    $response = $this->get(route('connection-check.download'), ['Accept-Encoding' => 'gzip'])->assertOk();

    expect(strlen($response->getContent()))->toBe(ConnectionCheckController::DOWNLOAD_BYTES)
        ->and($response->headers->get('Content-Type'))->toBe('application/octet-stream')
        ->and($response->headers->get('Content-Encoding'))->toBeNull()
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('running the check twice in a minute is not refused', function () {
    foreach (range(1, 2) as $run) {
        foreach (range(1, 20) as $ping) {
            $this->get(route('connection-check.ping'))->assertOk();
        }
        $this->get(route('connection-check.download'))->assertOk();
        $this->get(route('connection-check.download'))->assertOk();
    }
});

test('a student who still has to change the password can run the check', function () {
    $student = User::factory()->create(['role' => 'siswa', 'must_change_password' => true]);

    $this->actingAs($student)->get(route('connection-check'))->assertOk();
    $this->actingAs($student)->get(route('connection-check.ping'))->assertOk();
});

test('lan:serve warns when the server reaches the students over Wi-Fi only, and about AirDrop on a Mac', function () {
    $command = new class extends ServeLanCommand
    {
        public bool $awdl = true;

        protected function isAwdlActive(): bool
        {
            return $this->awdl;
        }

        public function warnings(array $interfaces): array
        {
            return $this->networkWarnings($interfaces);
        }
    };

    $wifiOnly = $command->warnings(['Wi-Fi (en1)' => '192.168.1.10']);
    expect($wifiOnly)->toHaveCount(2)
        ->and($wifiOnly[0])->toContain('kabel LAN')
        ->and($wifiOnly[1])->toContain('sudo ifconfig awdl0 down');

    expect($command->warnings(['Ethernet (en0)' => '192.168.1.10', 'Wi-Fi (en1)' => '192.168.1.11']))->toHaveCount(1)
        ->and($command->warnings(['Ethernet (en0)' => '192.168.1.10']))->toBe([]);

    $command->awdl = false;
    expect($command->warnings(['Ethernet (en0)' => '192.168.1.10', 'Wi-Fi (en1)' => '192.168.1.11']))->toBe([]);
});
