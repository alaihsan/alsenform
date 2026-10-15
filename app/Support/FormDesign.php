<?php

namespace App\Support;

/**
 * The look of an exam made with the Desainer Form add-on (like Pretty Forms Designer for Google
 * Forms): colours, header image and logo, fonts, shapes, width and custom CSS. The same design is
 * drawn in the teacher's editor and on every page the student sees. Whatever the editor sends is
 * normalised here, so the pages only ever receive known keys with safe values.
 */
class FormDesign
{
    /**
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'preset' => 'klasik',
        'accent' => '#4f46e5',
        'background' => '#f0efff',
        'backgroundImage' => null,
        'backgroundFit' => 'cover',
        'backgroundDim' => 0,
        'card' => '#ffffff',
        'cardOpacity' => 100,
        'text' => '#0f172a',
        'headerImage' => null,
        'logo' => null,
        'logoPosition' => 'left',
        'titleAlign' => 'left',
        'titleFont' => "'Inter', sans-serif",
        'bodyFont' => "'Inter', sans-serif",
        'titleSize' => 'md',
        'textSize' => 'md',
        'radius' => 'lg',
        'shadow' => 'soft',
        'cardBorder' => true,
        'width' => 'normal',
        'buttonShape' => 'rounded',
        'customCss' => '',
    ];

    /**
     * The choices of each setting that has a fixed set of them.
     *
     * @var array<string, list<string>>
     */
    public const CHOICES = [
        'backgroundFit' => ['cover', 'tile'],
        'logoPosition' => ['left', 'center', 'right'],
        'titleAlign' => ['left', 'center'],
        'titleSize' => ['sm', 'md', 'lg', 'xl'],
        'textSize' => ['sm', 'md', 'lg'],
        'radius' => ['none', 'sm', 'md', 'lg', 'xl'],
        'shadow' => ['none', 'soft', 'strong'],
        'width' => ['narrow', 'normal', 'wide'],
        'buttonShape' => ['square', 'rounded', 'pill'],
    ];

    public const COLORS = ['accent', 'background', 'card', 'text'];

    public const IMAGES = ['backgroundImage', 'headerImage', 'logo'];

    public const FONTS = ['titleFont', 'bodyFont'];

    public const MAX_CSS_LENGTH = 20000;

    /**
     * Stands for an allowed url( while the CSS is checked.
     */
    protected const URL_MARK = "\x01";

    public function __construct(protected MediaUrl $mediaUrl = new MediaUrl) {}

    /**
     * A complete design from what was stored: unknown keys dropped, bad values replaced by the defaults.
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $design): array
    {
        $design = is_array($design) ? $design : [];
        $normalized = self::DEFAULTS;

        $preset = $design['preset'] ?? null;
        $normalized['preset'] = is_string($preset) && preg_match('/^[a-z0-9-]{1,40}$/', $preset) === 1 ? $preset : null;

        foreach (self::COLORS as $key) {
            $color = $design[$key] ?? null;
            if (is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
                $normalized[$key] = strtolower($color);
            }
        }

        foreach (self::IMAGES as $key) {
            $normalized[$key] = $this->imageUrl($design[$key] ?? null);
        }

        foreach (self::FONTS as $key) {
            $font = $design[$key] ?? null;
            if (is_string($font) && preg_match("/^[A-Za-z0-9 ,'\\-]{1,120}$/", $font) === 1) {
                $normalized[$key] = $font;
            }
        }

        foreach (self::CHOICES as $key => $choices) {
            if (in_array($design[$key] ?? null, $choices, true)) {
                $normalized[$key] = $design[$key];
            }
        }

        $normalized['backgroundDim'] = $this->clamp($design['backgroundDim'] ?? null, 0, 80, self::DEFAULTS['backgroundDim']);
        $normalized['cardOpacity'] = $this->clamp($design['cardOpacity'] ?? null, 50, 100, self::DEFAULTS['cardOpacity']);
        $normalized['cardBorder'] = is_bool($design['cardBorder'] ?? null) ? $design['cardBorder'] : self::DEFAULTS['cardBorder'];
        $normalized['customCss'] = $this->sanitizeCss($design['customCss'] ?? '');

        return $normalized;
    }

    /**
     * Custom CSS without anything that could load from elsewhere or run code: no @import, no
     * url() but the school server's own images, no image-set(), expression() or bindings. Backslash
     * escapes are removed so none of these can be spelt another way. The page scopes what is left
     * to the exam's own elements.
     */
    public function sanitizeCss(mixed $css): string
    {
        if (! is_string($css)) {
            return '';
        }

        $css = mb_substr(str_replace(["\0", self::URL_MARK, '\\', '<'], '', $css), 0, self::MAX_CSS_LENGTH);
        $css = preg_replace('#/\*.*?(\*/|$)#s', '', $css) ?? '';
        $css = preg_replace('/@(import|charset|namespace)\b[^;{}]*;?/i', '', $css) ?? '';
        $css = preg_replace('/(expression|(-webkit-)?image-set|image|cross-fade|element|-moz-element|paint)\s*\(/i', 'blocked(', $css) ?? '';
        $css = preg_replace('/(javascript|vbscript)\s*:|behavior\s*:|-moz-binding/i', '', $css) ?? '';

        $css = preg_replace_callback('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/is', function (array $match): string {
            $target = trim($match[2]);
            $image = $this->imageUrl($target);
            if ($image !== null) {
                return self::URL_MARK.'("'.$image.'")';
            }

            return preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=]+$#', $target) === 1
                ? self::URL_MARK.'("'.$target.'")'
                : 'none';
        }, $css) ?? '';

        // An unclosed url( would swallow the rest of the sheet.
        $css = preg_replace('/url\s*\(/i', 'blocked(', $css) ?? '';

        return trim(str_replace(self::URL_MARK, 'url', $css));
    }

    /**
     * An image stored on this server (/storage/...), from its path or a URL to this application.
     */
    public function imageUrl(mixed $url): ?string
    {
        $url = $this->mediaUrl->normalize($url);

        return is_string($url) && preg_match('#^/storage/[A-Za-z0-9_\-/]+\.(webp|png|jpe?g|gif|svg)$#i', $url) === 1 && ! str_contains($url, '..')
            ? $url
            : null;
    }

    /**
     * What the dashboard card shows of the design.
     *
     * @param  array<string, mixed>  $design
     * @return array{accent: string, background: string, card: string, text: string, headerImage: ?string}
     */
    public function card(array $design): array
    {
        return [
            'accent' => $design['accent'],
            'background' => $design['background'],
            'card' => $design['card'],
            'text' => $design['text'],
            'headerImage' => $design['headerImage'],
        ];
    }

    protected function clamp(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) ? max($min, min($max, (int) round((float) $value))) : $default;
    }
}
