<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The address of the server on the internet, through a Cloudflare quick tunnel to lan:serve
 * (ONLINE_TUNNEL_METRICS, see config/app.php). The tunnel gets a new address
 * (https://<acak>.trycloudflare.com) whenever it starts again, so the address is read from the
 * tunnel itself, never stored: admins and teachers see the current one in the top bar.
 */
class OnlineAddress
{
    /**
     * Seconds an address read from the tunnel is used again: every open page of the teachers asks
     * for it, and a new address is seen within this time.
     */
    public const CACHE_SECONDS = 15;

    /**
     * Whether the server is meant to be opened on the internet: a tunnel is set up.
     */
    public function isConfigured(): bool
    {
        return $this->metrics() !== '';
    }

    /**
     * The address on the internet (https://<host>), or null when no tunnel is set up or it is not
     * running. Fresh: read from the tunnel now, as Kesehatan Server does.
     */
    public function url(bool $fresh = false): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $key = 'online-address:'.$this->metrics();

        if (! $fresh) {
            $remembered = rescue(fn (): mixed => Cache::get($key), null, report: false);

            if (is_array($remembered) && array_key_exists('url', $remembered)) {
                return is_string($remembered['url']) ? $remembered['url'] : null;
            }
        }

        $url = $this->read();
        // A stopped tunnel is remembered too, so it is not asked again by every page.
        rescue(fn () => Cache::put($key, ['url' => $url], self::CACHE_SECONDS), report: false);

        return $url;
    }

    /**
     * The address the tunnel reports on its metrics port, or null when it does not answer.
     */
    protected function read(): ?string
    {
        $hostname = rescue(fn (): mixed => Http::timeout(1)->get("http://{$this->metrics()}/quicktunnel")->json('hostname'), null, report: false);

        if (! is_string($hostname) || ! preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $hostname)) {
            return null;
        }

        return 'https://'.strtolower($hostname);
    }

    protected function metrics(): string
    {
        return trim((string) config('app.online_tunnel_metrics'));
    }
}
