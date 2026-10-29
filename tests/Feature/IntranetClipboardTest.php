<?php

use Illuminate\Support\Facades\File;

/*
 * Browsers offer navigator.clipboard only on HTTPS and localhost, so on the intranet address
 * (http://falcon.intranet:2026 or an IP) a direct call throws and the copy button does nothing. Every
 * copy goes through lib/clipboard.ts, which falls back to the older way of copying.
 */
test('only the shared copy helper calls navigator.clipboard', function () {
    $helper = resource_path('js/lib/clipboard.ts');

    $pagesCallingClipboard = collect(File::allFiles(resource_path('js')))
        ->filter(fn ($file): bool => in_array($file->getExtension(), ['vue', 'ts'], true))
        ->reject(fn ($file): bool => $file->getPathname() === $helper)
        ->filter(fn ($file): bool => str_contains(File::get($file->getPathname()), 'navigator.clipboard'))
        ->map(fn ($file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($pagesCallingClipboard)->toBe([]);
});
