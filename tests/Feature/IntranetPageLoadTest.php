<?php

use Illuminate\Support\Facades\File;

test('external web fonts never block or keep loading a page on an offline intranet', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match_all('/<link\b[^>]*>/i', $html, $matches);

    // A font request to the internet can hang for minutes on an intranet: even a "print" style
    // sheet kept the page loading. The fonts are added by a script once the page has loaded.
    $externalStylesheets = collect($matches[0])
        ->filter(fn (string $tag) => str_contains($tag, 'stylesheet')
            && preg_match('/href="(https?:\/\/[^"]+)"/i', $tag, $href)
            && ! str_starts_with($href[1], url('/')));

    expect($externalStylesheets)->toBeEmpty()
        ->and($html)->toContain("window.addEventListener('load'")
        ->and($html)->toContain('https://fonts.googleapis.com/css2?family=Amiri+Quran');
});

test('frontend styles do not import remote stylesheets', function () {
    $offenders = collect(File::allFiles(resource_path()))
        ->filter(fn ($file) => in_array($file->getExtension(), ['vue', 'css', 'ts'], true))
        ->filter(fn ($file) => preg_match('/@import\s+(url\()?["\']?https?:\/\//i', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
