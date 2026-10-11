<?php

namespace App\Support;

/**
 * Reads a browser's User-Agent header into the device, operating system and full browser name
 * shown in the activity report, without a Composer package.
 *
 * On plain HTTP (the school LAN server) browsers send no Client Hints, so Chrome on Android
 * reports its model as "K" and its version as "129.0.0.0": the model is then unknown and the
 * version is shortened to "129". Other browsers (Samsung Internet, Mi Browser...) still tell.
 */
class UserAgent
{
    /**
     * Browsers in the order they are recognised: browsers built on Chrome or Safari mention
     * those names too, so they come first. The first group of the pattern is the version.
     *
     * @var array<string, string>
     */
    protected const BROWSERS = [
        '/EdgA?\/([\d.]+)/' => 'Microsoft Edge',
        '/EdgiOS\/([\d.]+)/' => 'Microsoft Edge',
        '/Edge\/([\d.]+)/' => 'Microsoft Edge Legacy',
        '/Opera Mini\/([\d.]+)/' => 'Opera Mini',
        '/(?:OPR|OPX|OPT|OPiOS)\/([\d.]+)/' => 'Opera',
        '/SamsungBrowser\/([\d.]+)/' => 'Samsung Internet',
        '/MiuiBrowser\/([\d.]+)/' => 'Mi Browser',
        '/HuaweiBrowser\/([\d.]+)/' => 'Huawei Browser',
        '/HeyTapBrowser\/([\d.]+)/' => 'HeyTap Browser',
        '/VivoBrowser\/([\d.]+)/' => 'Vivo Browser',
        '/UCBrowser\/([\d.]+)/' => 'UC Browser',
        '/YaBrowser\/([\d.]+)/' => 'Yandex Browser',
        '/Vivaldi\/([\d.]+)/' => 'Vivaldi',
        '/DuckDuckGo\/([\d.]+)/' => 'DuckDuckGo',
        '/FBAV\/([\d.]+)/' => 'Facebook (browser dalam aplikasi)',
        '/Instagram ([\d.]+)/' => 'Instagram (browser dalam aplikasi)',
        '/(?:Firefox|FxiOS)\/([\d.]+)/' => 'Firefox',
        '/CriOS\/([\d.]+)/' => 'Chrome',
        '/HeadlessChrome\/([\d.]+)/' => 'Chrome Headless',
        '/Chromium\/([\d.]+)/' => 'Chromium',
        // An app's built-in browser on Android, e.g. a link opened from WhatsApp.
        '/; wv\).*Chrome\/([\d.]+)/' => 'Android WebView',
        '/Chrome\/([\d.]+)/' => 'Chrome',
        '/Android.*Version\/([\d.]+).*Safari\//' => 'Android Browser',
        '/Version\/([\d.]+).*Safari\//' => 'Safari',
        '/MSIE ([\d.]+)/' => 'Internet Explorer',
        '/Trident\/.*rv:([\d.]+)/' => 'Internet Explorer',
    ];

    /**
     * Windows versions by "Windows NT" number. Windows 11 still reports 10.0.
     *
     * @var array<string, string>
     */
    protected const WINDOWS_VERSIONS = [
        '10.0' => 'Windows 10/11',
        '6.3' => 'Windows 8.1',
        '6.2' => 'Windows 8',
        '6.1' => 'Windows 7',
        '6.0' => 'Windows Vista',
        '5.1' => 'Windows XP',
    ];

    /**
     * Brands of Android phones whose model code does not name them, by model code.
     *
     * @var array<string, string>
     */
    protected const ANDROID_BRANDS = [
        '/^(SM-|GT-|Galaxy)/i' => 'Samsung',
        '/^CPH\d/i' => 'OPPO',
        '/^RMX\d/i' => 'realme',
        '/^V\d{4}/' => 'vivo',
        '/^(Redmi|POCO|Mi |MI |M\d{4}[A-Z]|2\d{5,}[A-Z])/' => 'Xiaomi',
        '/^Pixel/i' => 'Google',
        '/^moto/i' => 'Motorola',
        '/^(LM-|LG-)/' => 'LG',
        '/^(ELE|VOG|ANE|MAR|LYA|JNY|NOH|JAD)-/' => 'Huawei',
    ];

    /**
     * @return array{deviceType: string, device: string|null, os: string|null, browser: string|null}
     */
    public function parse(?string $userAgent): array
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return ['deviceType' => 'unknown', 'device' => null, 'os' => null, 'browser' => null];
        }

        [$deviceType, $device] = $this->device($userAgent);

        return [
            'deviceType' => $deviceType,
            'device' => $device,
            'os' => $this->operatingSystem($userAgent),
            'browser' => $this->browser($userAgent),
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function device(string $userAgent): array
    {
        if (str_contains($userAgent, 'iPad')) {
            return ['tablet', 'iPad'];
        }

        if (str_contains($userAgent, 'iPhone')) {
            return ['mobile', 'iPhone'];
        }

        if (str_contains($userAgent, 'iPod')) {
            return ['mobile', 'iPod touch'];
        }

        if (str_contains($userAgent, 'Android') || str_contains($userAgent, 'HarmonyOS')) {
            $type = str_contains($userAgent, 'Mobile') ? 'mobile' : 'tablet';

            return [$type, $this->androidModel($userAgent) ?? ($type === 'mobile' ? 'HP Android' : 'Tablet Android')];
        }

        return match (true) {
            str_contains($userAgent, 'CrOS') => ['desktop', 'Chromebook'],
            str_contains($userAgent, 'Windows') => ['desktop', 'PC Windows'],
            str_contains($userAgent, 'Macintosh') => ['desktop', 'Mac'],
            str_contains($userAgent, 'Linux'), str_contains($userAgent, 'X11') => ['desktop', 'PC Linux'],
            default => ['unknown', null],
        };
    }

    /**
     * The model from "(Linux; Android 13; SM-A536E Build/TP1A...)", with its brand when the
     * model code does not name it ("Samsung SM-A536E"). Null when the browser hides it ("K").
     */
    protected function androidModel(string $userAgent): ?string
    {
        if (! preg_match('/\(([^)]*Android[^)]*)\)/', $userAgent, $matches)) {
            return null;
        }

        $parts = array_map('trim', explode(';', $matches[1]));
        $androidIndex = 0;
        foreach ($parts as $index => $part) {
            if (str_starts_with($part, 'Android')) {
                $androidIndex = $index;
                break;
            }
        }

        foreach (array_slice($parts, $androidIndex + 1) as $part) {
            $model = trim((string) preg_replace('/\s*Build\/.*$/', '', $part));

            // Skip a language ("id-id"), the WebView marker and Firefox's "Mobile; rv:131.0".
            if ($model === '' || preg_match('/^([a-z]{2}([-_][a-z]{2,4})?|wv|Mobile|Tablet|rv:.*|K)$/i', $model)) {
                continue;
            }

            $model = (string) preg_replace('/^SAMSUNG\s+/i', 'Samsung ', $model);

            foreach (self::ANDROID_BRANDS as $pattern => $brand) {
                if (preg_match($pattern, $model)) {
                    return "{$brand} {$model}";
                }
            }

            return $model;
        }

        return null;
    }

    protected function operatingSystem(string $userAgent): ?string
    {
        if (str_contains($userAgent, 'HarmonyOS')) {
            return 'HarmonyOS';
        }

        if (preg_match('/Android ([\d.]+)/', $userAgent, $matches)) {
            return 'Android '.$matches[1];
        }

        if (str_contains($userAgent, 'Android')) {
            return 'Android';
        }

        if (preg_match('/(iPhone|iPod|iPad).*? OS ([\d_]+)/', $userAgent, $matches)) {
            return ($matches[1] === 'iPad' ? 'iPadOS ' : 'iOS ').str_replace('_', '.', $matches[2]);
        }

        if (preg_match('/Windows NT ([\d.]+)/', $userAgent, $matches)) {
            return self::WINDOWS_VERSIONS[$matches[1]] ?? 'Windows';
        }

        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'CrOS') => 'ChromeOS',
            // Browsers freeze the macOS version at 10.15.7, so it is not shown.
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Ubuntu') => 'Ubuntu Linux',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }

    protected function browser(string $userAgent): ?string
    {
        foreach (self::BROWSERS as $pattern => $name) {
            if (preg_match($pattern, $userAgent, $matches)) {
                // "129.0.0.0" (a version Chrome shortens on purpose) reads as "129".
                $version = (string) preg_replace('/(\.0)+$/', '', $matches[1]);

                return trim("{$name} {$version}");
            }
        }

        if (str_contains($userAgent, 'AppleWebKit') && preg_match('/iPhone|iPad|iPod/', $userAgent)) {
            return 'Browser dalam aplikasi (iOS)';
        }

        return null;
    }
}
