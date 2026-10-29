<?php

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\GzipResponseMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestClientHints;
use App\Http\Middleware\StaticAssetCacheMiddleware;
use App\Http\Middleware\TrustLocalProxies;
use App\Support\ErrorPage;
use App\Support\ServerErrorRecorder;
use App\Support\StaleFrameworkCaches;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->replace(TrustProxies::class, TrustLocalProxies::class);

        $middleware->web(append: [
            StaticAssetCacheMiddleware::class,
            GzipResponseMiddleware::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsurePasswordIsChanged::class,
            RequestClientHints::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Server errors are kept for the admin (Galat Server), each with the reference code that
        // its error page shows and its line in laravel.log carries.
        $exceptions->dontReportDuplicates();
        $exceptions->report(fn (Throwable $exception) => app(ServerErrorRecorder::class)->record($exception));
        $exceptions->context(fn (Throwable $exception): array => ['reference' => app(ServerErrorRecorder::class)->referenceFor($exception)]);

        $exceptions->respond(fn (Response $response, Throwable $exception, Request $request): Response => app(ErrorPage::class)->respond($response, $exception, $request));
    })->create();

// Caches built from older code (e.g. before "git pull") are dropped before they are loaded.
$app->beforeBootstrapping(LoadConfiguration::class, fn (Application $app) => StaleFrameworkCaches::clear($app));

return $app;
