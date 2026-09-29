<?php

use App\Http\Middleware\GzipResponseMiddleware;
use App\Services\ImageOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('exam:optimize command runs successfully', function () {
    $this->artisan('exam:optimize')
        ->assertSuccessful()
        ->expectsOutputToContain('OPTIMASI ALSENFORM UNTUK UJIAN SERENTAK');
});

test('lan:serve command includes workers and optimize options', function () {
    $this->artisan('lan:serve --help')
        ->assertSuccessful()
        ->expectsOutputToContain('--workers')
        ->expectsOutputToContain('--optimize');
});

test('image optimization service optimizes and scales uploaded image', function () {
    Storage::fake('public');

    // Create a dummy 100x100 PNG
    $imageResource = imagecreatetruecolor(100, 100);
    $bgColor = imagecolorallocate($imageResource, 50, 100, 150);
    imagefilledrectangle($imageResource, 0, 0, 100, 100, $bgColor);

    ob_start();
    imagepng($imageResource);
    $pngBytes = ob_get_clean();
    imagedestroy($imageResource);

    $tempFile = tempnam(sys_get_temp_dir(), 'test_img_');
    file_put_contents($tempFile, $pngBytes);

    $uploadedFile = new UploadedFile($tempFile, 'test.png', 'image/png', null, true);

    $service = new ImageOptimizationService;
    $storedPath = $service->optimizeAndStore($uploadedFile, 'media', 'public');

    expect($storedPath)->toBeString();
    expect(Storage::disk('public')->exists($storedPath))->toBeTrue();

    if (file_exists($tempFile)) {
        @unlink($tempFile);
    }
});

test('image optimization service optimizes raw bytes', function () {
    Storage::fake('public');

    $imageResource = imagecreatetruecolor(80, 80);
    $color = imagecolorallocate($imageResource, 200, 50, 50);
    imagefilledrectangle($imageResource, 0, 0, 80, 80, $color);

    ob_start();
    imagejpeg($imageResource);
    $jpgBytes = ob_get_clean();
    imagedestroy($imageResource);

    $service = new ImageOptimizationService;
    $storedPath = $service->optimizeAndStoreBytes($jpgBytes, 'jpg', 'media', 'public');

    expect($storedPath)->toBeString();
    expect(Storage::disk('public')->exists($storedPath))->toBeTrue();
});

test('gzip response middleware compresses large text and json responses when gzip accepted', function () {
    $middleware = new GzipResponseMiddleware;

    $request = Request::create('/test-endpoint', 'GET');
    $request->headers->set('Accept-Encoding', 'gzip, deflate');

    // Response larger than 1024 bytes
    $largeContent = str_repeat('Hello Alsenform Fast Intranet Performance! ', 40);
    $response = new Response($largeContent, 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
    ]);

    $processedResponse = $middleware->handle($request, fn () => $response);

    expect($processedResponse->headers->get('Content-Encoding'))->toBe('gzip');
    expect($processedResponse->headers->get('Vary'))->toBe('Accept-Encoding');
    expect(strlen($processedResponse->getContent()))->toBeLessThan(strlen($largeContent));
});
