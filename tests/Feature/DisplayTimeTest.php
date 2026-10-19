<?php

use App\Models\QuizForm;
use App\Models\User;
use App\Support\DisplayTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Every time the app writes for people is in the display timezone and in Indonesian, whatever
 * the application's or Carbon's locale: one helper, no global setting to fall back from.
 */
afterEach(function () {
    Carbon::setTestNow();
    App::setLocale(config('app.locale'));
});

test('times are written in indonesian in the display timezone, also after the locale changes', function () {
    config(['app.display_timezone' => 'Asia/Jakarta']);
    Carbon::setTestNow('2026-10-19 10:00:00');

    // A mail or notification sent in English switches the locale for a moment.
    App::setLocale('en');
    Carbon::setLocale('en');

    expect(DisplayTime::format(Carbon::parse('2026-10-16 18:30:00'), 'j M Y H.i'))->toBe('17 Okt 2026 01.30')
        ->and(DisplayTime::format(Carbon::parse('2026-08-17 03:00:00'), 'l, j F Y'))->toBe('Senin, 17 Agustus 2026')
        ->and(DisplayTime::ago(now()->subMinutes(5)))->toBe('5 menit yang lalu')
        ->and(DisplayTime::ago(null))->toBeNull()
        ->and(DisplayTime::now()->format('Y-m-d H:i T'))->toBe('2026-10-19 17:00 WIB');
});

test('the form cards and the editor show indonesian dates', function () {
    config(['app.display_timezone' => 'Asia/Jakarta']);
    Carbon::setTestNow('2026-10-19 10:00:00');
    App::setLocale('en');

    $teacher = User::factory()->create(['role' => 'guru']);
    $form = QuizForm::factory()->for($teacher)->create(['published_at' => now()->subHours(3)]);
    $form->forceFill(['updated_at' => Carbon::parse('2026-10-16 20:00:00')])->saveQuietly();

    $this->actingAs($teacher)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('recentForms.0.updatedLabel', '17 Okt 2026'));

    $student = User::factory()->create(['role' => 'siswa', 'nis' => '880011']);
    $this->actingAs($student)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('recentForms.0.updatedLabel', '3 jam yang lalu'));
});
