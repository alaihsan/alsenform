<?php

use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

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
        ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', false)
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
        ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', false)
        ->assertDontSee('Alsenform');
});

test('the icon files are in public', function () {
    $icon = file_get_contents(public_path('falcon-forms-icon.svg'));
    $favicon = file_get_contents(public_path('favicon.ico'));

    expect($icon)->toContain('<title>Falcon Forms</title>')
        ->and(substr_count($icon, '<path '))->toBe(3)
        // The small icon is the falcon as one white silhouette, legible at 16 pixels.
        ->and(substr_count(file_get_contents(public_path('favicon.svg')), '<path '))->toBe(1)
        // An icon file with its sizes (16, 32 and 48 pixels), not the empty placeholder.
        ->and(substr($favicon, 0, 4))->toBe("\x00\x00\x01\x00")
        ->and(unpack('v', substr($favicon, 4, 2))[1])->toBe(3)
        ->and(getimagesize(public_path('apple-touch-icon.png')))->toMatchArray([0 => 180, 1 => 180, 'mime' => 'image/png']);
});

test('the code is MIT licensed by Falcon Education Partner', function () {
    expect(file_get_contents(base_path('LICENSE')))
        ->toStartWith('MIT License')
        ->toContain('Copyright (c) 2026 Falcon Education Partner');
});

test('everyone can read the privacy policy, with the contact of the institution', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Privacy')->where('contact', null));

    config(['app.privacy_contact' => 'privasi@sekolah.sch.id']);

    $this->actingAs(User::factory()->create(['role' => 'siswa', 'nis' => '777002']))
        ->get(route('privacy'))
        ->assertInertia(fn (Assert $page) => $page->where('contact', 'privasi@sekolah.sch.id'));
});

test('the finish screen says "Dibuat dengan Falcon Forms" unless the teacher turns it off', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $exam = QuizForm::factory()->for($teacher)->create(['published_at' => now(), 'settings' => ['isQuiz' => true]]);

    // Shown by default: the setting is absent.
    expect($exam->settings)->not->toHaveKey('showMadeWith');

    $this->actingAs($teacher)->patch(route('forms.update', $exam), [
        'title' => $exam->title,
        'slug' => $exam->slug,
        'questions' => $exam->questions,
        'settings' => [...$exam->settings, 'showMadeWith' => false],
        'published' => true,
    ])->assertSessionHasNoErrors();

    expect($exam->fresh()->settings['showMadeWith'])->toBeFalse();

    $this->get(route('forms.public', $exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.settings.showMadeWith', false));
});
