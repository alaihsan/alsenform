<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Reads the device, operating system and full browser name shown in the activity report from a
 * browser's User-Agent header, made exact with what the browser tells besides, without a Composer
 * package.
 *
 * Chrome (and Edge) shorten their User-Agent: the phone model reads "K", Android is always "10",
 * Windows 11 says Windows 10 and the version is "129.0.0.0". The real values come in User-Agent
 * Client Hints, which browsers only send over HTTPS: the Sec-CH-UA-* headers (asked for by the
 * RequestClientHints middleware) and the device report the pages send (X-Device-Hints, see
 * resources/js/lib/deviceHints.ts). On plain HTTP (the school LAN server) a hidden model or OS
 * version is reported as unknown, never guessed. The device report also tells, on any connection,
 * an iPad that says it is a Mac, an Android tablet in desktop mode and Brave, which looks like Chrome.
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
        '/PHX\/([\d.]+)/' => 'Phoenix Browser',
        '/UCBrowser\/([\d.]+)/' => 'UC Browser',
        '/YaBrowser\/([\d.]+)/' => 'Yandex Browser',
        '/Vivaldi\/([\d.]+)/' => 'Vivaldi',
        '/DuckDuckGo\/([\d.]+)/' => 'DuckDuckGo',
        '/FBAV\/([\d.]+)/' => 'Facebook (browser dalam aplikasi)',
        '/Instagram ([\d.]+)/' => 'Instagram (browser dalam aplikasi)',
        '/Line\/([\d.]+)/' => 'LINE (browser dalam aplikasi)',
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
     * Brands of Android phones and tablets whose model code does not name them, by model code.
     *
     * @var array<string, string>
     */
    protected const ANDROID_BRANDS = [
        '/^(SM-|GT-|SC-\d|SCV\d|Galaxy)/i' => 'Samsung',
        '/^CPH\d/i' => 'OPPO',
        '/^RMX\d/i' => 'realme',
        '/^V\d{4}/' => 'vivo',
        '/^(Redmi|POCO|Mi |MI |M\d{4}[A-Z]|2\d{4,}[A-Z])/' => 'Xiaomi',
        '/^Pixel/i' => 'Google',
        '/^(moto|XT\d{4})/i' => 'Motorola',
        '/^(LM-|LG-)/' => 'LG',
        '/^(SO-\d|SOV\d|XQ-[A-Z]{2}\d)/' => 'Sony',
        '/^(IN20|KB20|LE21|LE22|NE22|DN21)\d{2}$/' => 'OnePlus',
        '/^(ELE|VOG|ANE|MAR|LYA|JNY|NOH|JAD|STK|DUB|MRD|POT|AMN|JKM|INE|SNE|HMA|EML|CLT|YAL|DRA|MED|ART|PPA|AGS\d?|BAH\d?)-/' => 'Huawei',
    ];

    /**
     * Brands that name themselves in the model, written the way the brand does.
     *
     * @var array<string, string>
     */
    protected const NAMED_BRANDS = [
        '/^SAMSUNG[\s_-]+/i' => 'Samsung ',
        '/^HUAWEI[\s_-]+/i' => 'Huawei ',
        '/^HONOR[\s_-]+/i' => 'HONOR ',
        '/^OPPO[\s_-]+/i' => 'OPPO ',
        '/^vivo[\s_-]+/i' => 'vivo ',
        '/^Infinix[\s_-]+/i' => 'Infinix ',
        '/^TECNO[\s_-]+/i' => 'TECNO ',
        '/^itel[\s_-]+/i' => 'itel ',
        '/^Nokia[\s_-]+/i' => 'Nokia ',
        '/^ASUS[\s_-]+/i' => 'ASUS ',
        '/^Lenovo[\s_-]+/i' => 'Lenovo ',
        '/^ONEPLUS[\s_-]+/i' => 'OnePlus ',
        '/^Advan[\s_-]+/i' => 'Advan ',
    ];

    /**
     * Names of browsers in Client Hints, as shown in the report.
     *
     * @var array<string, string>
     */
    protected const HINT_BRANDS = [
        'Google Chrome' => 'Chrome',
        'Microsoft Edge' => 'Microsoft Edge',
        'Opera' => 'Opera',
        'Opera GX' => 'Opera GX',
        'Brave' => 'Brave',
        'Samsung Internet' => 'Samsung Internet',
        'Yandex' => 'Yandex Browser',
        'YaBrowser' => 'Yandex Browser',
        'Android WebView' => 'Android WebView',
        'HeadlessChrome' => 'Chrome Headless',
    ];

    /**
     * @param  array<string, mixed>  $hints  What the browser tells besides its User-Agent (see hintsFrom()).
     * @return array{deviceType: string, device: string|null, os: string|null, browser: string|null}
     */
    public function parse(?string $userAgent, array $hints = []): array
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return ['deviceType' => 'unknown', 'device' => null, 'os' => null, 'browser' => null];
        }

        [$deviceType, $device] = $this->device($userAgent);

        return $this->withHints([
            'deviceType' => $deviceType,
            'device' => $device,
            'os' => $this->operatingSystem($userAgent),
            'browser' => $this->browser($userAgent),
        ], $userAgent, $hints);
    }

    /**
     * What the browser tells besides its User-Agent: the Client Hint headers (HTTPS only) and the
     * device report sent by the pages. Each value is checked; anything malformed is left out.
     *
     * @return array{model?: string, platform?: string, platformVersion?: string, mobile?: bool, browsers?: list<array{brand: string, version: string}>, touch?: int, navigatorPlatform?: string, screen?: array{0: int, 1: int}, brave?: bool}
     */
    public function hintsFrom(Request $request): array
    {
        $report = $this->deviceReport($request->header('X-Device-Hints'));
        $hints = [];

        $model = $this->quoted($request->header('Sec-CH-UA-Model')) ?? $this->text($report['model'] ?? null);
        if ($model !== null && $model !== '') {
            $hints['model'] = $model;
        }

        $platform = $this->quoted($request->header('Sec-CH-UA-Platform')) ?? $this->text($report['platformName'] ?? null);
        if ($platform !== null && $platform !== '') {
            $hints['platform'] = $platform;
        }

        $platformVersion = $this->quoted($request->header('Sec-CH-UA-Platform-Version')) ?? $this->text($report['platformVersion'] ?? null);
        if ($platformVersion !== null && preg_match('/^\d+(\.\d+){0,3}$/', $platformVersion)) {
            $hints['platformVersion'] = $platformVersion;
        }

        $mobile = $request->header('Sec-CH-UA-Mobile');
        if ($mobile === '?1' || $mobile === '?0') {
            $hints['mobile'] = $mobile === '?1';
        } elseif (is_bool($report['mobile'] ?? null)) {
            $hints['mobile'] = $report['mobile'];
        }

        $browsers = $this->brandList($request->header('Sec-CH-UA-Full-Version-List')) ?: $this->reportedBrands($report['browsers'] ?? null);
        if ($browsers !== []) {
            $hints['browsers'] = $browsers;
        }

        if (is_int($report['touch'] ?? null) && $report['touch'] >= 0) {
            $hints['touch'] = min($report['touch'], 100);
        }

        $navigatorPlatform = $this->text($report['platform'] ?? null);
        if ($navigatorPlatform !== null && $navigatorPlatform !== '') {
            $hints['navigatorPlatform'] = $navigatorPlatform;
        }

        $screen = $report['screen'] ?? null;
        if (is_array($screen) && count($screen) === 2 && is_int($screen[0]) && is_int($screen[1]) && $screen[0] > 0 && $screen[1] > 0) {
            $hints['screen'] = [min($screen[0], 20000), min($screen[1], 20000)];
        }

        if (($report['brave'] ?? false) === true) {
            $hints['brave'] = true;
        }

        return $hints;
    }

    /**
     * @param  array{deviceType: string, device: string|null, os: string|null, browser: string|null}  $parsed
     * @param  array<string, mixed>  $hints
     * @return array{deviceType: string, device: string|null, os: string|null, browser: string|null}
     */
    protected function withHints(array $parsed, string $userAgent, array $hints): array
    {
        $platform = $hints['platform'] ?? null;
        $touch = $hints['touch'] ?? 0;

        // Browsers on an iPad ask for desktop sites and say it is a Mac (an iPhone too, when asked
        // to); a Mac has no touch screen. The screen tells an iPhone (narrower than 600) from an iPad.
        $isAppleTouchDevice = str_contains($userAgent, 'Macintosh') && $touch > 0;

        if ($isAppleTouchDevice) {
            $version = preg_match('/Version\/([\d.]+)/', $userAgent, $matches) ? ' '.$matches[1] : '';
            $isPhone = isset($hints['screen']) && min($hints['screen']) < 600;

            $parsed = [
                ...$parsed,
                'deviceType' => $isPhone ? 'mobile' : 'tablet',
                'device' => $isPhone ? 'iPhone' : 'iPad',
                'os' => ($isPhone ? 'iOS' : 'iPadOS').$version,
            ];
        }

        // Chrome on Android in desktop mode says it is a Linux PC; its processor and touch screen tell.
        if (str_contains($userAgent, 'X11') && ! str_contains($userAgent, 'CrOS') && $touch > 0
            && preg_match('/arm|aarch64/i', $hints['navigatorPlatform'] ?? '')) {
            $isPhone = isset($hints['screen']) && min($hints['screen']) < 600;
            $parsed = [
                ...$parsed,
                'deviceType' => $isPhone ? 'mobile' : 'tablet',
                'device' => ($isPhone ? 'HP Android' : 'Tablet Android').' (mode desktop)',
                'os' => 'Android',
            ];
        }

        if (isset($hints['model'])) {
            $isAndroid = $platform === 'Android' || str_contains($userAgent, 'Android');
            $parsed['device'] = $isAndroid ? $this->brandedModel($hints['model']) : $hints['model'];
            if ($isAndroid) {
                $parsed['deviceType'] = ($hints['mobile'] ?? str_contains($userAgent, 'Mobile')) ? 'mobile' : 'tablet';
            }
        }

        // Hints that do not match the User-Agent (another browser or system) are not used.
        if (isset($hints['platformVersion']) && ! $isAppleTouchDevice && $this->platformMatches($platform, $userAgent)) {
            $parsed['os'] = $this->hintedOperatingSystem($platform, $hints['platformVersion']) ?? $parsed['os'];
        }

        if (isset($hints['browsers']) && str_contains($userAgent, 'Chrome/')) {
            $parsed['browser'] = $this->hintedBrowser($hints['browsers'], $parsed['browser']) ?? $parsed['browser'];
        }

        // Brave looks like Chrome in every header; only the page can tell.
        if (($hints['brave'] ?? false) && is_string($parsed['browser']) && str_starts_with($parsed['browser'], 'Chrome ')) {
            $parsed['browser'] = 'Brave '.substr($parsed['browser'], strlen('Chrome '));
        }

        return $parsed;
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

            return [$type, $this->androidModel($userAgent) ?? ($type === 'mobile' ? 'HP Android' : 'Tablet Android').' (merek tidak dikirim browser)'];
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

            return $this->brandedModel($model);
        }

        return null;
    }

    /**
     * A model with its brand: "SM-A536E" is "Samsung SM-A536E", "ASUS_X00TD" is "ASUS X00TD".
     */
    protected function brandedModel(string $model): string
    {
        foreach (self::NAMED_BRANDS as $pattern => $brand) {
            if (preg_match($pattern, $model)) {
                return (string) preg_replace($pattern, $brand, $model);
            }
        }

        foreach (self::ANDROID_BRANDS as $pattern => $brand) {
            if (preg_match($pattern, $model)) {
                return "{$brand} {$model}";
            }
        }

        return $model;
    }

    protected function operatingSystem(string $userAgent): ?string
    {
        if (str_contains($userAgent, 'HarmonyOS')) {
            return 'HarmonyOS';
        }

        // Chrome's shortened User-Agent always says Android 10: the real version is unknown.
        if (preg_match('/Android 10; K\)/', $userAgent)) {
            return 'Android';
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

    /**
     * Whether the Client Hints platform is the system the User-Agent names.
     */
    protected function platformMatches(?string $platform, string $userAgent): bool
    {
        return match ($platform) {
            'Windows' => str_contains($userAgent, 'Windows'),
            'macOS' => str_contains($userAgent, 'Macintosh'),
            'Android' => str_contains($userAgent, 'Android'),
            'Chrome OS', 'ChromeOS' => str_contains($userAgent, 'CrOS'),
            default => false,
        };
    }

    /**
     * The exact operating system from the Client Hints platform and its version.
     */
    protected function hintedOperatingSystem(?string $platform, string $version): ?string
    {
        $parts = array_map('intval', explode('.', $version));
        $short = (string) preg_replace('/(\.0)+$/', '', $version);

        return match ($platform) {
            // Windows 11 reports 13 and up, Windows 10 1 to 12; 0.x are Windows 7, 8 and 8.1.
            'Windows' => match (true) {
                $parts[0] >= 13 => 'Windows 11',
                $parts[0] >= 1 => 'Windows 10',
                ($parts[1] ?? 0) === 3 => 'Windows 8.1',
                ($parts[1] ?? 0) === 2 => 'Windows 8',
                default => 'Windows 7',
            },
            'macOS' => 'macOS '.$short,
            'Android' => 'Android '.$short,
            'Chrome OS', 'ChromeOS' => 'ChromeOS '.$short,
            default => null,
        };
    }

    /**
     * The browser and its full version from the Client Hints brand list ("Google Chrome",
     * "Microsoft Edge"...). Kept as read from the User-Agent when the list only says Chromium.
     *
     * @param  list<array{brand: string, version: string}>  $browsers
     */
    protected function hintedBrowser(array $browsers, ?string $fromUserAgent): ?string
    {
        foreach ($browsers as $browser) {
            $name = self::HINT_BRANDS[$browser['brand']] ?? null;

            if ($name === null) {
                continue;
            }

            // An app's built-in browser keeps its name; only its version is completed.
            if (is_string($fromUserAgent) && str_starts_with($fromUserAgent, 'Android WebView') && $name === 'Chrome') {
                $name = 'Android WebView';
            }

            return "{$name} {$browser['version']}";
        }

        return null;
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

    /**
     * The device report a page sends: URL-encoded JSON, at most 2 KB.
     *
     * @return array<string, mixed>
     */
    protected function deviceReport(?string $header): array
    {
        if ($header === null || $header === '' || strlen($header) > 2048) {
            return [];
        }

        $report = json_decode(rawurldecode($header), true, 4);

        return is_array($report) ? $report : [];
    }

    /**
     * A structured-header string ("\"SM-A536E\""), unquoted.
     */
    protected function quoted(?string $header): ?string
    {
        if ($header === null || ! preg_match('/^\s*"([^"]{0,100})"\s*$/', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    protected function text(mixed $value): ?string
    {
        return is_string($value) && mb_strlen($value) <= 100 && ! preg_match('/[\x00-\x1F]/', $value) ? trim($value) : null;
    }

    /**
     * The real brands of a Sec-CH-UA-Full-Version-List header, without the made-up one browsers
     * add ("Not A;Brand") and without Chromium, which every Chrome-based browser lists.
     *
     * @return list<array{brand: string, version: string}>
     */
    protected function brandList(?string $header): array
    {
        if ($header === null || strlen($header) > 1000) {
            return [];
        }

        preg_match_all('/"([^"]{1,60})"\s*;\s*v\s*=\s*"([\d.]{1,30})"/', $header, $matches, PREG_SET_ORDER);

        return $this->realBrands(array_map(fn (array $match): array => ['brand' => $match[1], 'version' => $match[2]], $matches));
    }

    /**
     * @return list<array{brand: string, version: string}>
     */
    protected function reportedBrands(mixed $browsers): array
    {
        if (! is_array($browsers)) {
            return [];
        }

        $brands = [];
        foreach (array_slice($browsers, 0, 10) as $browser) {
            $brand = $this->text(is_array($browser) ? ($browser['brand'] ?? null) : null);
            $version = is_array($browser) ? ($browser['version'] ?? null) : null;

            if ($brand !== null && is_string($version) && preg_match('/^[\d.]{1,30}$/', $version)) {
                $brands[] = ['brand' => $brand, 'version' => $version];
            }
        }

        return $this->realBrands($brands);
    }

    /**
     * @param  list<array{brand: string, version: string}>  $brands
     * @return list<array{brand: string, version: string}>
     */
    protected function realBrands(array $brands): array
    {
        return array_values(array_filter(
            $brands,
            fn (array $brand): bool => $brand['brand'] !== 'Chromium' && ! preg_match('/not.?a.?brand/i', $brand['brand']),
        ));
    }
}
