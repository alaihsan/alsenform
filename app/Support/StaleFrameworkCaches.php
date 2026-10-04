<?php

namespace App\Support;

use Illuminate\Foundation\Application;

/**
 * Removes framework caches (built by exam:optimize, route:cache or config:cache) that are older
 * than the files they were built from. After "git pull" a stale route cache misses new routes, so
 * every page linking to them fails with HTTP 500 until someone runs "php artisan optimize:clear".
 * This runs before the caches are loaded, so the request simply boots from the current files.
 */
class StaleFrameworkCaches
{
    /**
     * Delete the route and config caches when their source files changed after caching.
     */
    public static function clear(Application $app): void
    {
        $routesCache = $app->getCachedRoutesPath();
        if (is_file($routesCache)) {
            self::removeIfOlderThan($routesCache, glob($app->basePath('routes/*.php')) ?: []);
        }

        $configCache = $app->getCachedConfigPath();
        if (is_file($configCache)) {
            self::removeIfOlderThan($configCache, [...(glob($app->configPath('*.php')) ?: []), $app->environmentFilePath()]);
        }
    }

    /**
     * @param  list<string>  $sources
     */
    protected static function removeIfOlderThan(string $cache, array $sources): void
    {
        $cachedAt = @filemtime($cache);
        if ($cachedAt === false) {
            return;
        }

        foreach ($sources as $source) {
            if (is_file($source) && filemtime($source) > $cachedAt) {
                // Another worker may have removed it a moment ago.
                if (is_file($cache)) {
                    @unlink($cache);
                }

                return;
            }
        }
    }
}
