<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Intranet Address
    |--------------------------------------------------------------------------
    |
    | The name students and teachers type on the school network, served by
    | "php artisan lan:serve" on port 2026: http://falcon.intranet:2026.
    | The router's DNS must point this name at the server (a static DNS
    | entry). Leave it empty to only announce the server's IP addresses.
    |
    */

    'lan_host' => env('LAN_HOST', 'falcon.intranet'),

    /*
    |--------------------------------------------------------------------------
    | Intranet Address Behind Caddy
    |--------------------------------------------------------------------------
    |
    | With Caddy (a reverse proxy) on this computer in front of several web
    | apps, students open the name on Caddy's port: 80 gives
    | http://falcon.intranet, 443 https://falcon.intranet. lan:serve keeps
    | listening on 2026 for Caddy. "php artisan lan:caddy" writes the site.
    | Leave it empty to open http://falcon.intranet:2026 directly.
    |
    */

    'lan_public_port' => env('LAN_PUBLIC_PORT'),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Tunnels and reverse proxies that run on this machine (Expose, Herd /
    | Nginx) are always trusted so URLs and HTTPS are detected correctly.
    | Add extra proxy IP addresses here (comma separated) when needed.
    | LAN clients are never trusted, so they cannot spoof their IP; for
    | that reason the wildcards "*" and "**" are not supported.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Display Timezone
    |--------------------------------------------------------------------------
    |
    | Times are stored in UTC. Reports such as the downloaded exam results
    | show them in the school's local time (WIB by default; use
    | Asia/Makassar for WITA or Asia/Jayapura for WIT).
    |
    */

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Donations
    |--------------------------------------------------------------------------
    |
    | The developer donation endpoints only simulate a QRIS payment: they
    | mark a donation as paid without any money being checked. They stay
    | off until they are connected to a licensed payment provider.
    |
    */

    'donations' => (bool) env('DONATIONS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Privacy Contact
    |--------------------------------------------------------------------------
    |
    | Where people ask the institution running this server about their data
    | (an email or phone number), shown on the Kebijakan Privasi page. Left
    | empty, the page tells them to contact the institution's admin.
    |
    */

    'privacy_contact' => env('PRIVACY_CONTACT'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
