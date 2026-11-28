<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * Alamat online: the address of the server on the internet, through the Cloudflare quick tunnel,
 * in the top bar of admins and teachers. The tunnel gets a new address whenever it starts again,
 * and the pages ask for it every half minute, so the new one shows by itself.
 */

beforeEach(function () {
    config(['app.online_tunnel_metrics' => '127.0.0.1:20241']);
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->tunnelHostname = null;
    Http::fake(['127.0.0.1:20241/quicktunnel' => fn () => $this->tunnelHostname === null
        ? Http::response('', 503)
        : Http::response(['hostname' => $this->tunnelHostname])]);
});

/**
 * The tunnel answering with this address from now on, or not answering (null).
 */
function tunnelAt(?string $hostname): void
{
    test()->tunnelHostname = $hostname;
}

test('admins and teachers get the address the tunnel has now', function () {
    tunnelAt('letting-collected-computational-dirt.trycloudflare.com');
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

    foreach ([$admin, $this->teacher] as $user) {
        $this->actingAs($user)->getJson(route('online-address'))
            ->assertOk()
            ->assertExactJson(['configured' => true, 'url' => 'https://letting-collected-computational-dirt.trycloudflare.com']);
    }
});

test('students and guests do not get it', function () {
    tunnelAt('letting-collected-computational-dirt.trycloudflare.com');

    $this->actingAs(User::factory()->create(['role' => 'siswa', 'nis' => '2026001']))->getJson(route('online-address'))->assertForbidden();
    auth()->logout();
    $this->getJson(route('online-address'))->assertUnauthorized();
});

test('a new address of the tunnel shows within a quarter of a minute, without asking the tunnel for every page', function () {
    tunnelAt('first-address-here.trycloudflare.com');
    $this->actingAs($this->teacher)->getJson(route('online-address'))->assertJsonPath('url', 'https://first-address-here.trycloudflare.com');

    // The tunnel started again with another address.
    tunnelAt('second-address-now.trycloudflare.com');
    $this->actingAs($this->teacher)->getJson(route('online-address'))->assertJsonPath('url', 'https://first-address-here.trycloudflare.com');
    Http::assertSentCount(1);

    $this->travel(16)->seconds();
    $this->actingAs($this->teacher)->getJson(route('online-address'))->assertJsonPath('url', 'https://second-address-now.trycloudflare.com');
});

test('a stopped tunnel says so, and the server only on the school network has nothing to show', function () {
    tunnelAt(null);
    $this->actingAs($this->teacher)->getJson(route('online-address'))->assertExactJson(['configured' => true, 'url' => null]);

    config(['app.online_tunnel_metrics' => null]);
    $this->actingAs($this->teacher)->getJson(route('online-address'))->assertExactJson(['configured' => false, 'url' => null]);
    Http::assertSentCount(1);
});

test('an answer of the tunnel that is not a host name is not shown', function () {
    tunnelAt('evil.example/"><script>');

    $this->actingAs($this->teacher)->getJson(route('online-address'))->assertJsonPath('url', null);
});
