<?php

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\GzipResponseMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\StaticAssetCacheMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            StaticAssetCacheMiddleware::class,
            GzipResponseMiddleware::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsurePasswordIsChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
