<?php

use Symfony\Component\Process\Process;

/*
 * The colours of the four looks (resources/js/theme/palette.js): text stays comfortable to read
 * on every surface, the dark looks keep their dimmed backdrops and their light inverted surfaces,
 * and the exam pages, form designs and print get the standard light colours back.
 */

/**
 * The looks and their CSS, as Tailwind builds them.
 *
 * @return array{looks: array<string, array<string, mixed>>, styles: array<string, mixed>}
 */
function themePalette(): array
{
    static $palette = null;

    if ($palette === null) {
        $process = new Process(
            ['node', '--input-type=module', '-e', "const m = await import('./resources/js/theme/palette.js'); process.stdout.write(JSON.stringify({ looks: m.LOOKS, styles: m.lookStyles() }));"],
            base_path(),
        );
        $process->mustRun();
        $palette = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    return $palette;
}

/** The WCAG contrast ratio of two colours. */
function contrastRatio(string $first, string $second): float
{
    $luminance = function (string $hex): float {
        $channels = array_map(function (int $offset) use ($hex): float {
            $value = hexdec(substr($hex, $offset, 2)) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, [1, 3, 5]);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };

    $lighter = max($luminance($first), $luminance($second));
    $darker = min($luminance($first), $luminance($second));

    return ($lighter + 0.05) / ($darker + 0.05);
}

test('text reads comfortably on the cards and on the page in every look', function (string $look) {
    $colors = themePalette()['looks'][$look];
    $text = $colors['text']['slate'];

    foreach ([$colors['surface']['white'], $colors['canvas'], $colors['surface']['slate']['50']] as $surface) {
        // Headings softer than pure white in the dark looks, yet well past WCAG AAA (7).
        expect(contrastRatio($text['900'], $surface))->toBeGreaterThanOrEqual(10)
            ->and(contrastRatio($text['700'], $surface))->toBeGreaterThanOrEqual(7)
            ->and(contrastRatio($text['500'], $surface))->toBeGreaterThanOrEqual(4.3);
    }
})->with(['standard', 'warm', 'standard-dark', 'warm-dark']);

test('in the dark looks, accent text reads on the cards and on its own tinted background', function (string $look) {
    $colors = themePalette()['looks'][$look];

    foreach (['indigo', 'violet', 'blue', 'sky', 'emerald', 'amber', 'red', 'rose'] as $accent) {
        expect(contrastRatio($colors['text'][$accent]['600'], $colors['surface']['white']))->toBeGreaterThanOrEqual(4.5, "{$accent}-600")
            ->and(contrastRatio($colors['text'][$accent]['700'], $colors['surface'][$accent]['50']))->toBeGreaterThanOrEqual(4.5, "{$accent}-700 on {$accent}-50");
    }
})->with(['standard-dark', 'warm-dark']);

test('the warm look trades indigo for terracotta that white text still reads on', function () {
    $warm = themePalette()['looks']['warm'];

    expect($warm['surface']['indigo']['600'])->not->toBe(themePalette()['looks']['standard']['surface']['indigo']['600'])
        ->and(contrastRatio('#ffffff', $warm['surface']['indigo']['600']))->toBeGreaterThanOrEqual(4.5)
        ->and(contrastRatio($warm['text']['indigo']['600'], $warm['surface']['white']))->toBeGreaterThanOrEqual(4.5);
});

test('in the dark looks, dark buttons and toasts turn light with dark text, and backdrops stay dark', function (string $look) {
    $colors = themePalette()['looks'][$look];

    expect(contrastRatio($colors['inverseText'], $colors['surface']['slate']['900']))->toBeGreaterThanOrEqual(7)
        ->and(contrastRatio('#000000', $colors['surface']['slate']['950']))->toBeLessThan(1.3);
})->with(['standard-dark', 'warm-dark']);

test('exam pages, form designs and print get the standard light colours back', function () {
    $styles = themePalette()['styles'];
    $root = $styles[':root'];

    foreach (['.theme-light', '.af-designed', '@media print'] as $selector) {
        $light = $selector === '@media print' ? reset($styles[$selector]) : $styles[$selector];

        foreach ($styles['.dark'] as $variable => $value) {
            if (str_starts_with($variable, '--')) {
                expect($light[$variable] ?? null)->toBe($root[$variable], "{$selector} {$variable}");
            }
        }
    }

    expect($styles['.theme-light']['colorScheme'])->toBe('light')
        ->and($styles['.dark']['colorScheme'])->toBe('dark');
});

test('the standard light look keeps the colours of before', function () {
    $root = themePalette()['styles'][':root'];

    expect($root['--c-white'])->toBe('255 255 255')
        ->and($root['--c-slate-900'])->toBe('15 23 42')
        ->and($root['--t-slate-500'])->toBe('100 116 139')
        ->and($root['--c-indigo-600'])->toBe('79 70 229')
        ->and($root['--c-canvas'])->toBe('245 244 255')
        ->and($root['--background'])->toBe('0 0% 100%');
});
