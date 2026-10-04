<?php

use App\Support\StaleFrameworkCaches;
use Illuminate\Foundation\Application;

beforeEach(function () {
    $this->basePath = sys_get_temp_dir().'/alsen-stale-cache-'.uniqid();
    foreach (['routes', 'config', 'bootstrap/cache'] as $directory) {
        mkdir($this->basePath.'/'.$directory, 0777, true);
    }

    $this->app = new Application($this->basePath);
    $this->routesCache = $this->app->getCachedRoutesPath();
    $this->configCache = $this->app->getCachedConfigPath();

    $now = time();
    foreach (['routes/web.php', 'config/app.php', '.env'] as $source) {
        file_put_contents($this->basePath.'/'.$source, '<?php');
        touch($this->basePath.'/'.$source, $now - 3600);
    }
    foreach ([$this->routesCache, $this->configCache] as $cache) {
        file_put_contents($cache, '<?php return [];');
        touch($cache, $now - 60);
    }
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->basePath));
});

test('caches built after the last change are kept', function () {
    StaleFrameworkCaches::clear($this->app);

    expect($this->routesCache)->toBeFile()
        ->and($this->configCache)->toBeFile();
});

test('a route cache older than the route files is removed after an update', function () {
    touch($this->basePath.'/routes/web.php', time());

    StaleFrameworkCaches::clear($this->app);

    expect(file_exists($this->routesCache))->toBeFalse()
        ->and($this->configCache)->toBeFile();
});

test('a config cache older than the config files or .env is removed', function (string $changedFile) {
    touch($this->basePath.'/'.$changedFile, time());

    StaleFrameworkCaches::clear($this->app);

    expect(file_exists($this->configCache))->toBeFalse()
        ->and($this->routesCache)->toBeFile();
})->with(['config/app.php', '.env']);

test('nothing happens when the application is not cached', function () {
    unlink($this->routesCache);
    unlink($this->configCache);

    StaleFrameworkCaches::clear($this->app);

    expect(file_exists($this->routesCache))->toBeFalse();
});
