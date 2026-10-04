<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_proxy-probe', fn (Request $request) => [
        'root' => url('/'),
        'asset' => asset('build/app.js'),
        'ip' => $request->ip(),
        'secure' => $request->secure(),
    ]);
});

/**
 * Headers sent by an Expose tunnel (or any reverse proxy) in front of the app.
 *
 * @return array<string, string>
 */
function exposeHeaders(): array
{
    return [
        'X-Forwarded-For' => '36.71.10.20',
        'X-Forwarded-Host' => 'ujian-sekolah.sharedwithexpose.com',
        'X-Forwarded-Proto' => 'https',
    ];
}

test('expose tunnel connecting from loopback generates public https urls', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(exposeHeaders())
        ->getJson('/_proxy-probe');

    $response->assertOk()
        ->assertJson([
            'root' => 'https://ujian-sekolah.sharedwithexpose.com',
            'asset' => 'https://ujian-sekolah.sharedwithexpose.com/build/app.js',
            'ip' => '36.71.10.20',
            'secure' => true,
        ]);
});

test('lan clients cannot spoof forwarded headers', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.45'])
        ->withHeaders(exposeHeaders())
        ->getJson('/_proxy-probe');

    $response->assertOk()
        ->assertJson([
            'ip' => '192.0.2.45',
            'secure' => false,
        ]);

    expect($response->json('root'))->not->toContain('sharedwithexpose.com');
});

test('tunnel pointed at the current lan address of this machine is trusted', function () {
    $localAddress = collect(net_get_interfaces())
        ->flatMap(fn (array $interface) => $interface['unicast'] ?? [])
        ->pluck('address')
        ->first(fn ($address) => is_string($address)
            && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && ! str_starts_with($address, '127.'));

    if (! $localAddress) {
        $this->markTestSkipped('No non-loopback IPv4 address available on this machine.');
    }

    $this->withServerVariables(['REMOTE_ADDR' => $localAddress])
        ->withHeaders(exposeHeaders())
        ->getJson('/_proxy-probe')
        ->assertOk()
        ->assertJson([
            'root' => 'https://ujian-sekolah.sharedwithexpose.com',
            'secure' => true,
        ]);
});

test('additional proxies can be trusted through configuration', function () {
    config(['app.trusted_proxies' => '10.10.0.1, 10.10.0.2']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.10.0.2'])
        ->withHeaders(exposeHeaders())
        ->getJson('/_proxy-probe')
        ->assertOk()
        ->assertJson([
            'ip' => '36.71.10.20',
            'secure' => true,
        ]);
});

test('a wildcard trusted proxy setting never trusts lan clients', function () {
    config(['app.trusted_proxies' => '*']);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.45'])
        ->withHeaders(exposeHeaders())
        ->getJson('/_proxy-probe')
        ->assertOk()
        ->assertJson([
            'ip' => '192.0.2.45',
            'secure' => false,
        ]);
});
