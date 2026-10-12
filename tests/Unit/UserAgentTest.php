<?php

use App\Support\UserAgent;
use Illuminate\Http\Request;

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
        // Chrome says Android 10 and model "K" on every phone: both unknown, never guessed.
        'mobile', 'HP Android (merek tidak dikirim browser)', 'Android', 'Chrome 129',
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
    'chrome on an xiaomi that still sends its model code' => [
        'Mozilla/5.0 (Linux; Android 13; 23021RAAEG Build/TKQ1.221114.001) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.5563.116 Mobile Safari/537.36',
        'mobile', 'Xiaomi 23021RAAEG', 'Android 13', 'Chrome 111.0.5563.116',
    ],
    'a link opened inside whatsapp on an infinix' => [
        'Mozilla/5.0 (Linux; Android 12; Infinix X6816 Build/SP1A.210812.016; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/127.0.6533.103 Mobile Safari/537.36',
        'mobile', 'Infinix X6816', 'Android 12', 'Android WebView 127.0.6533.103',
    ],
    'opera on android' => [
        'Mozilla/5.0 (Linux; Android 13; RMX3630) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36 OPR/79.0.4195.76555',
        'mobile', 'realme RMX3630', 'Android 13', 'Opera 79.0.4195.76555',
    ],
    'firefox on android' => [
        'Mozilla/5.0 (Android 14; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0',
        'mobile', 'HP Android (merek tidak dikirim browser)', 'Android 14', 'Firefox 131',
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

/**
 * @param  array<string, string>  $headers
 */
function agentOf(string $userAgent, array $headers = []): array
{
    $server = ['HTTP_USER_AGENT' => $userAgent];
    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }
    $request = Request::create('/', 'POST', server: $server);

    return (new UserAgent)->parse($request->userAgent(), (new UserAgent)->hintsFrom($request));
}

const REDUCED_CHROME_ANDROID = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36';
const REDUCED_CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

test('over HTTPS the client hints give the real phone model, Android version and browser version', function () {
    expect(agentOf(REDUCED_CHROME_ANDROID, [
        'Sec-CH-UA-Model' => '"SM-A536E"',
        'Sec-CH-UA-Platform' => '"Android"',
        'Sec-CH-UA-Platform-Version' => '"14.0.0"',
        'Sec-CH-UA-Mobile' => '?1',
        'Sec-CH-UA-Full-Version-List' => '"Google Chrome";v="129.0.6668.100", "Not=A?Brand";v="8.0.0.0", "Chromium";v="129.0.6668.100"',
    ]))->toBe([
        'deviceType' => 'mobile',
        'device' => 'Samsung SM-A536E',
        'os' => 'Android 14',
        'browser' => 'Chrome 129.0.6668.100',
    ]);
});

test('the client hints tell Windows 11 from Windows 10, and Edge with its full version', function () {
    $edge = REDUCED_CHROME_WINDOWS.' Edg/129.0.0.0';

    expect(agentOf($edge, [
        'Sec-CH-UA-Platform' => '"Windows"',
        'Sec-CH-UA-Platform-Version' => '"15.0.0"',
        'Sec-CH-UA-Full-Version-List' => '"Microsoft Edge";v="129.0.2792.79", "Not=A?Brand";v="8.0.0.0", "Chromium";v="129.0.6668.90"',
    ]))->toMatchArray(['os' => 'Windows 11', 'browser' => 'Microsoft Edge 129.0.2792.79'])
        ->and(agentOf(REDUCED_CHROME_WINDOWS, ['Sec-CH-UA-Platform' => '"Windows"', 'Sec-CH-UA-Platform-Version' => '"10.0.0"']))
        ->toMatchArray(['os' => 'Windows 10']);
});

test('the device report of the page completes what the user agent hides', function () {
    $report = fn (array $values): string => rawurlencode(json_encode($values));

    // Read by the page from navigator.userAgentData (HTTPS).
    expect(agentOf(REDUCED_CHROME_ANDROID, ['X-Device-Hints' => $report([
        'touch' => 5, 'platform' => 'Linux armv81', 'screen' => [393, 873],
        'model' => 'RMX3630', 'platformName' => 'Android', 'platformVersion' => '13.0.0', 'mobile' => true,
        'browsers' => [['brand' => 'Not A(Brand', 'version' => '99.0.0.0'], ['brand' => 'Google Chrome', 'version' => '129.0.6668.81']],
    ])]))->toBe(['deviceType' => 'mobile', 'device' => 'realme RMX3630', 'os' => 'Android 13', 'browser' => 'Chrome 129.0.6668.81']);

    // An iPad asking for desktop sites says it is a Mac; its touch screen tells.
    expect(agentOf('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', [
        'X-Device-Hints' => $report(['touch' => 5, 'platform' => 'MacIntel', 'screen' => [820, 1180]]),
    ]))->toBe(['deviceType' => 'tablet', 'device' => 'iPad', 'os' => 'iPadOS 17.5', 'browser' => 'Safari 17.5']);

    // Hints of another browser or system than the User-Agent names are not used.
    expect(agentOf('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', [
        'Sec-CH-UA-Platform' => '"Linux"',
        'Sec-CH-UA-Platform-Version' => '"6.5.0"',
        'Sec-CH-UA-Full-Version-List' => '"HeadlessChrome";v="141.0.7390.37"',
        'X-Device-Hints' => $report(['touch' => 1, 'platform' => 'MacIntel']),
    ]))->toBe(['deviceType' => 'tablet', 'device' => 'iPad', 'os' => 'iPadOS 17.5', 'browser' => 'Safari 17.5']);

    // A real Mac stays a Mac.
    expect(agentOf('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', [
        'X-Device-Hints' => $report(['touch' => 0, 'platform' => 'MacIntel', 'screen' => [1512, 982]]),
    ]))->toMatchArray(['deviceType' => 'desktop', 'device' => 'Mac']);

    // Chrome on an Android tablet in desktop mode says it is a Linux PC.
    expect(agentOf('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', [
        'X-Device-Hints' => $report(['touch' => 10, 'platform' => 'Linux aarch64', 'screen' => [800, 1280]]),
    ]))->toMatchArray(['deviceType' => 'tablet', 'device' => 'Tablet Android (mode desktop)', 'os' => 'Android']);

    // Brave looks like Chrome in every header.
    expect(agentOf(REDUCED_CHROME_WINDOWS, ['X-Device-Hints' => $report(['touch' => 0, 'brave' => true])]))
        ->toMatchArray(['browser' => 'Brave 129']);
});

test('malformed hints are ignored', function () {
    expect(agentOf(REDUCED_CHROME_ANDROID, [
        'Sec-CH-UA-Model' => 'SM-A536E',
        'Sec-CH-UA-Platform-Version' => '"14; DROP TABLE"',
        'X-Device-Hints' => '%7Bnot json',
    ]))->toBe(['deviceType' => 'mobile', 'device' => 'HP Android (merek tidak dikirim browser)', 'os' => 'Android', 'browser' => 'Chrome 129'])
        ->and(agentOf(REDUCED_CHROME_ANDROID, ['X-Device-Hints' => str_repeat('a', 3000)]))->toMatchArray(['os' => 'Android']);
});
