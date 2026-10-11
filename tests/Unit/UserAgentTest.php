<?php

use App\Support\UserAgent;

test('the device, operating system and full browser name are read from the user agent', function (string $userAgent, string $deviceType, ?string $device, ?string $os, ?string $browser) {
    expect((new UserAgent)->parse($userAgent))->toBe([
        'deviceType' => $deviceType,
        'device' => $device,
        'os' => $os,
        'browser' => $browser,
    ]);
})->with([
    'chrome on android (model hidden)' => [
        'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36',
        'mobile', 'HP Android', 'Android 10', 'Chrome 129',
    ],
    'samsung internet' => [
        'Mozilla/5.0 (Linux; Android 13; SAMSUNG SM-A536E) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36',
        'mobile', 'Samsung SM-A536E', 'Android 13', 'Samsung Internet 26',
    ],
    'chrome on a samsung phone that still sends its model' => [
        'Mozilla/5.0 (Linux; Android 11; SM-A515F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.5615.135 Mobile Safari/537.36',
        'mobile', 'Samsung SM-A515F', 'Android 11', 'Chrome 112.0.5615.135',
    ],
    'mi browser on a redmi' => [
        'Mozilla/5.0 (Linux; U; Android 12; id-id; Redmi Note 11 Build/SKQ1.211103.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/112.0.5615.136 Mobile Safari/537.36 XiaoMi/MiuiBrowser/14.10.1-gn',
        'mobile', 'Xiaomi Redmi Note 11', 'Android 12', 'Mi Browser 14.10.1',
    ],
    'a link opened inside whatsapp' => [
        'Mozilla/5.0 (Linux; Android 11; CPH2239 Build/RP1A.200720.011; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/128.0.6613.146 Mobile Safari/537.36',
        'mobile', 'OPPO CPH2239', 'Android 11', 'Android WebView 128.0.6613.146',
    ],
    'opera on android' => [
        'Mozilla/5.0 (Linux; Android 13; RMX3630) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36 OPR/79.0.4195.76555',
        'mobile', 'realme RMX3630', 'Android 13', 'Opera 79.0.4195.76555',
    ],
    'firefox on android' => [
        'Mozilla/5.0 (Android 14; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0',
        'mobile', 'HP Android', 'Android 14', 'Firefox 131',
    ],
    'android tablet' => [
        'Mozilla/5.0 (Linux; Android 13; SM-X200) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        'tablet', 'Samsung SM-X200', 'Android 13', 'Chrome 129',
    ],
    'safari on iphone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        'mobile', 'iPhone', 'iOS 17.5.1', 'Safari 17.5',
    ],
    'chrome on ipad' => [
        'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/129.0.6668.69 Mobile/15E148 Safari/604.1',
        'tablet', 'iPad', 'iPadOS 17.5', 'Chrome 129.0.6668.69',
    ],
    'edge on windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.2792.79',
        'desktop', 'PC Windows', 'Windows 10/11', 'Microsoft Edge 129.0.2792.79',
    ],
    'chrome on windows 7' => [
        'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36',
        'desktop', 'PC Windows', 'Windows 7', 'Chrome 109',
    ],
    'safari on a mac' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15',
        'desktop', 'Mac', 'macOS', 'Safari 17.6',
    ],
    'chromebook' => [
        'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        'desktop', 'Chromebook', 'ChromeOS', 'Chrome 129',
    ],
    'firefox on ubuntu' => [
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0',
        'desktop', 'PC Linux', 'Ubuntu Linux', 'Firefox 131',
    ],
]);

test('a missing user agent is reported as unknown', function () {
    expect((new UserAgent)->parse(null))->toBe(['deviceType' => 'unknown', 'device' => null, 'os' => null, 'browser' => null])
        ->and((new UserAgent)->parse('curl/8.5.0'))->toBe(['deviceType' => 'unknown', 'device' => null, 'os' => null, 'browser' => null]);
});
