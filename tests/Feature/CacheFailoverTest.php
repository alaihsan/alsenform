<?php

use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter as Limits;

/*
 * The cache runs on Redis with the database behind it (CACHE_STORE=failover, config/cache.php).
 * The rate limits of signing in and of exam pages live in the cache: while Redis is off they are
 * kept in the database, so a class can still sign in and work.
 */
beforeEach(function () {
    // Nothing listens on port 1: Redis is off.
    config(['database.redis.cache.port' => 1, 'database.redis.default.port' => 1]);

    // The limiter of a test is made at boot, on the array cache; on the server it is on this one.
    Limits::swap(new RateLimiter(Cache::store('failover')));
});

test('the cache is Redis, with the database behind it', function () {
    expect(config('cache.stores.failover'))->toBe(['driver' => 'failover', 'stores' => ['redis', 'database']]);
});

test('rate limits keep counting in the database while Redis is off', function () {
    expect(Limits::hit('exam-page', 60))->toBe(1)
        ->and(Limits::hit('exam-page', 60))->toBe(2)
        ->and(Limits::tooManyAttempts('exam-page', 2))->toBeTrue()
        ->and(DB::table('cache')->where('key', 'like', '%exam-page%')->exists())->toBeTrue();
});

test('signing in keeps working while Redis is off', function () {
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '2024301', 'password' => Hash::make('rahasia123')]);

    $this->post(route('login'), ['identifier' => '2024301', 'password' => 'salah'])->assertSessionHasErrors();
    expect(Limits::attempts('2024301|127.0.0.1'))->toBe(1);

    $this->post(route('login'), ['identifier' => '2024301', 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($student);
});
