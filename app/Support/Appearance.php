<?php

namespace App\Support;

use App\Models\User;

/**
 * The look of the pages (Profil → Tampilan): light, dark, the device's setting, or dark in the
 * evening by the device's clock, in the standard or the warm colours. The page carries the
 * account's choice on <html>; the script in app.blade.php paints with it before anything shows,
 * and resources/js/composables/useAppearance.ts keeps it up to date while the page is open.
 */
final class Appearance
{
    /** @var list<string> */
    public const APPEARANCES = ['light', 'dark', 'system', 'schedule'];

    /** @var list<string> */
    public const COLOR_THEMES = ['standard', 'warm'];

    /**
     * Pages that always show the standard light look: the exam and its results keep the design
     * their teacher made, whatever look the student's device prefers. Each calls useLightOnlyPage().
     *
     * @var list<string>
     */
    public const LIGHT_ONLY_PAGES = ['PublicQuiz', 'QuizResult'];

    /**
     * The attributes of <html> for a page: the account's choice (none when it has not chosen, so
     * the device's own is used) and whether the page always shows the light look.
     *
     * @param  array{component?: string}|null  $page
     * @return array{appearance: ?string, colorTheme: ?string, lightOnly: bool}
     */
    public static function forPage(?User $user, ?array $page): array
    {
        $appearance = $user?->getAttribute('appearance');
        $colorTheme = $user?->getAttribute('color_theme');

        return [
            'appearance' => in_array($appearance, self::APPEARANCES, true) ? $appearance : null,
            'colorTheme' => in_array($colorTheme, self::COLOR_THEMES, true) ? $colorTheme : null,
            'lightOnly' => in_array($page['component'] ?? null, self::LIGHT_ONLY_PAGES, true),
        ];
    }
}
