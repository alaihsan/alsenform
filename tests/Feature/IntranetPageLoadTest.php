<?php

use Illuminate\Support\Facades\File;

test('external web fonts never block rendering on an offline intranet', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match_all('/<link\b[^>]*>/i', $html, $matches);

    $externalStylesheets = collect($matches[0])
        ->filter(fn (string $tag) => str_contains($tag, 'stylesheet')
            && preg_match('/href="(https?:\/\/[^"]+)"/i', $tag, $href)
            && ! str_starts_with($href[1], url('/')));

    expect($externalStylesheets)->not->toBeEmpty();

    $externalStylesheets->each(
        fn (string $tag) => expect($tag)->toContain('media="print"')->toContain("onload=\"this.media='all'\"")
    );
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
