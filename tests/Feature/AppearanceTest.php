<?php

use App\Models\QuizForm;
use App\Models\User;
use App\Support\Appearance;

/*
 * Profil → Tampilan: light, dark, the device's setting or dark in the evening ("Ikuti jam"), in the
 * standard or the warm colours. Kept on the account, painted before the page shows, and never on
 * the exam pages, which keep the design their teacher made.
 */

/** The <html> tag of a page. */
function htmlTag(string $page): string
{
    preg_match('/<html\b[^>]*>/s', $page, $match);

    return preg_replace('/\s+/', ' ', $match[0] ?? '');
}

test('a user chooses the look of their pages, kept on their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('profile.appearance.update'), ['appearance' => 'dark', 'color_theme' => 'warm'])
        ->assertNoContent();

    expect($user->fresh())
        ->appearance->toBe('dark')
        ->color_theme->toBe('warm');
});

test('one choice changes without touching the other', function () {
    $user = User::factory()->create(['appearance' => 'schedule', 'color_theme' => 'warm']);

    $this->actingAs($user)
        ->patchJson(route('profile.appearance.update'), ['color_theme' => 'standard'])
        ->assertNoContent();

    expect($user->fresh())
        ->appearance->toBe('schedule')
        ->color_theme->toBe('standard');
});

test('only the known looks are kept', function () {
    $user = User::factory()->create(['appearance' => 'light', 'color_theme' => 'standard']);

    $this->actingAs($user)
        ->patchJson(route('profile.appearance.update'), ['appearance' => 'sepia', 'color_theme' => 'neon'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['appearance', 'color_theme']);

    expect($user->fresh())
        ->appearance->toBe('light')
        ->color_theme->toBe('standard');
});

test('a guest has no account to keep a look on', function () {
    $this->patchJson(route('profile.appearance.update'), ['appearance' => 'dark'])->assertUnauthorized();
});

test('every page carries the look of the account, so it is painted before anything shows', function () {
    $user = User::factory()->create(['appearance' => 'dark', 'color_theme' => 'warm']);

    $page = $this->actingAs($user)->get('/settings/profile')->assertOk()->getContent();

    expect(htmlTag($page))
        ->toContain('class="dark"')
        ->toContain('data-appearance="dark"')
        ->toContain('data-color-theme="warm"')
        ->toContain('data-theme="warm"')
        ->not->toContain('data-light-only')
        ->and($page)->toContain("stored('appearance')");
});

test('without a choice on the account, the device decides', function () {
    $user = User::factory()->create();

    $tag = htmlTag($this->actingAs($user)->get('/settings/profile')->getContent());

    expect($tag)
        ->not->toContain('data-appearance')
        ->not->toContain('data-color-theme')
        ->not->toContain('dark');
});

test('the exam page keeps the standard light look whatever the account chose', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'appearance' => 'dark', 'color_theme' => 'warm']);
    $exam = QuizForm::factory()->for($teacher)->create(['published_at' => now(), 'settings' => ['isQuiz' => true]]);

    $tag = htmlTag($this->actingAs($teacher)->get(route('forms.public', $exam->slug))->assertOk()->getContent());

    expect($tag)
        ->toContain('data-light-only')
        ->not->toContain('class="dark"')
        ->not->toContain('data-theme="warm"');
});

test('each light-only page holds the light look itself when reached inside the app', function () {
    foreach (Appearance::LIGHT_ONLY_PAGES as $component) {
        expect(file_get_contents(resource_path("js/pages/{$component}.vue")))->toContain('useLightOnlyPage();');
    }
});

test('Tampilan is part of the profile', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/appearance')
        ->assertRedirect('/settings/profile#tampilan');
});

test('the first paint and the open page agree on the evening of "Ikuti jam"', function () {
    $script = file_get_contents(resource_path('views/partials/appearance-script.blade.php'));
    $composable = file_get_contents(resource_path('js/composables/useAppearance.ts'));

    expect($script)->toContain("appearance === 'schedule' && (hour >= 18 || hour < 6)")
        ->and($composable)->toContain('SCHEDULE_DARK_FROM_HOUR = 18')
        ->and($composable)->toContain('SCHEDULE_DARK_UNTIL_HOUR = 6');
});

test('the error page of a full page load follows the look of the device too', function () {
    $this->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertSee("stored('appearance')", false)
        ->assertSee(':root.dark', false);
});
