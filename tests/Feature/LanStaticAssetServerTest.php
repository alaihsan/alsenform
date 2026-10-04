<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->assetDirectory = public_path('__lan-test');
    File::ensureDirectoryExists($this->assetDirectory);

    $this->script = str_repeat("console.log('Alsenform intranet');\n", 200);
    File::put($this->assetDirectory.'/app.js', $this->script);
    File::put($this->assetDirectory.'/video.mp4', random_bytes(4096));

    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $this->port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    $this->server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$this->port, base_path('server.php')], base_path());
    $this->server->start();

    $deadline = microtime(true) + 5;
    while (microtime(true) < $deadline && ! @fsockopen('127.0.0.1', $this->port)) {
        usleep(50_000);
    }
});

afterEach(function () {
    $this->server->stop(0);
    File::deleteDirectory($this->assetDirectory);
});

/**
 * Perform a raw HTTP request against the LAN server without decoding the body.
 *
 * @param  array<string, string>  $headers
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function lanRequest(int $port, string $path, array $headers = []): array
{
    $context = stream_context_create(['http' => [
        'header' => collect($headers)->map(fn ($value, $name) => "{$name}: {$value}")->implode("\r\n"),
        'ignore_errors' => true,
        'timeout' => 5,
    ]]);

    $body = (string) file_get_contents("http://127.0.0.1:{$port}{$path}", false, $context);

    $responseHeaders = [];
    foreach (array_slice($http_response_header, 1) as $line) {
        [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
        $responseHeaders[strtolower(trim($name))] = trim($value);
    }

    preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);

    return ['status' => (int) $status[1], 'headers' => $responseHeaders, 'body' => $body];
}

test('pre-compressed build assets are served when the browser accepts gzip', function () {
    File::put($this->assetDirectory.'/app.js.gz', gzencode($this->script, 9));

    $response = lanRequest($this->port, '/__lan-test/app.js', ['Accept-Encoding' => 'gzip, deflate']);

    expect($response['status'])->toBe(200)
        ->and($response['headers']['content-encoding'])->toBe('gzip')
        ->and($response['headers']['vary'])->toBe('Accept-Encoding')
        ->and(gzdecode($response['body']))->toBe($this->script);
});

test('text assets are compressed on the fly and stay plain for clients without gzip', function () {
    $compressed = lanRequest($this->port, '/__lan-test/app.js', ['Accept-Encoding' => 'gzip']);
    $plain = lanRequest($this->port, '/__lan-test/app.js', ['Accept-Encoding' => 'identity']);

    expect($compressed['headers']['content-encoding'])->toBe('gzip')
        ->and(strlen($compressed['body']))->toBeLessThan(strlen($this->script))
        ->and(gzdecode($compressed['body']))->toBe($this->script)
        ->and($plain['headers'])->not->toHaveKey('content-encoding')
        ->and($plain['body'])->toBe($this->script);
});

test('unchanged assets are revalidated with 304 instead of being downloaded again', function () {
    $first = lanRequest($this->port, '/__lan-test/app.js', ['Accept-Encoding' => 'gzip']);

    $revalidated = lanRequest($this->port, '/__lan-test/app.js', [
        'Accept-Encoding' => 'gzip',
        'If-None-Match' => $first['headers']['etag'],
    ]);

    expect($revalidated['status'])->toBe(304)
        ->and($revalidated['body'])->toBe('');
});

test('videos support byte ranges so they play on iOS safari', function () {
    $video = File::get($this->assetDirectory.'/video.mp4');

    $partial = lanRequest($this->port, '/__lan-test/video.mp4', ['Range' => 'bytes=100-1123']);
    $outOfRange = lanRequest($this->port, '/__lan-test/video.mp4', ['Range' => 'bytes=999999-']);

    expect($partial['status'])->toBe(206)
        ->and($partial['headers']['content-range'])->toBe('bytes 100-1123/4096')
        ->and($partial['headers']['accept-ranges'])->toBe('bytes')
        ->and($partial['body'])->toBe(substr($video, 100, 1024))
        ->and($outOfRange['status'])->toBe(416);
});
