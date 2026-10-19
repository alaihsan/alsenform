<?php

use Illuminate\Support\Facades\Route;

/*
 * Falcon Forms, by Falcon Education Partner: the name and the falcon icon on every page, in the
 * browser tab and on a phone's home screen. The icons are plain files in public/, so they also
 * show when the build is missing.
 */
test('every page carries the falcon forms name and icon', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('<title inertia>Falcon Forms</title>', false)
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="32x32">', false)
        ->assertSee('<link rel="icon" href="/falcon-forms-icon.svg" type="image/svg+xml">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false)
        ->assertDontSee('Alsenform');
});

test('the error pages show the falcon forms icon without the build', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('_test/missing', fn () => abort(404));

    $this->get('/_test/missing')
        ->assertNotFound()
        ->assertSee('<title>Halaman Tidak Ditemukan - Falcon Forms</title>', false)
        ->assertSee('src="/falcon-forms-icon.svg"', false)
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="32x32">', false)
        ->assertDontSee('Alsenform');
});

test('the icon files are in public', function () {
    $icon = file_get_contents(public_path('falcon-forms-icon.svg'));
    $favicon = file_get_contents(public_path('favicon.ico'));

    expect($icon)->toContain('<title>Falcon Forms</title>')
        ->and(substr_count($icon, '<path '))->toBe(3)
        // An icon file with its sizes (16, 32 and 48 pixels), not the empty placeholder.
        ->and(substr($favicon, 0, 4))->toBe("\x00\x00\x01\x00")
        ->and(unpack('v', substr($favicon, 4, 2))[1])->toBe(3)
        ->and(getimagesize(public_path('apple-touch-icon.png')))->toMatchArray([0 => 180, 1 => 180, 'mime' => 'image/png']);
});
