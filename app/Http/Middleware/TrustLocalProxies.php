<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Trust forwarded headers only from proxies running on this machine.
 *
 * Expose (and Herd / Nginx) connect to the application from the server itself,
 * so their X-Forwarded-Host / X-Forwarded-Proto headers are honoured and the
 * application generates the public tunnel URL (https) instead of the local one.
 * Requests coming straight from LAN clients are never trusted, so students can
 * not spoof their IP address to bypass rate limits or proctoring logs.
 */
class TrustLocalProxies extends TrustProxies
{
    /**
     * Loopback addresses that are always trusted.
     *
     * @var list<string>
     */
    protected const LOOPBACK_PROXIES = ['127.0.0.1', '::1'];

    /**
     * Sets the trusted proxies on the request.
     */
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        // Wildcards ("*" / "**") are ignored on purpose: students connect to this server
        // directly over the LAN, so trusting every peer would let them spoof their IP.
        $configured = array_values(array_filter(
            $this->configuredProxies(),
            fn (string $proxy): bool => ! in_array($proxy, ['*', '**'], true),
        ));

        $trusted = [...self::LOOPBACK_PROXIES, ...$configured];
        $remoteAddress = (string) $request->server->get('REMOTE_ADDR');

        if ($remoteAddress !== ''
            && $this->hasForwardedHeaders($request)
            && ! IpUtils::checkIp($remoteAddress, $trusted)
            && in_array($remoteAddress, $this->localAddresses(), true)) {
            $trusted[] = $remoteAddress;
        }

        $request->setTrustedProxies($trusted, $this->getTrustedHeaderNames());
    }

    /**
     * Additional proxies configured through TRUSTED_PROXIES.
     *
     * @return list<string>
     */
    protected function configuredProxies(): array
    {
        $configured = config('app.trusted_proxies', '');

        if (is_array($configured)) {
            return array_values(array_filter(array_map('trim', $configured)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $configured))));
    }

    /**
     * Determine if the request carries any proxy forwarding header.
     */
    protected function hasForwardedHeaders(Request $request): bool
    {
        foreach (['X-Forwarded-For', 'X-Forwarded-Host', 'X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-Prefix'] as $header) {
            if ($request->headers->has($header)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The IP addresses currently assigned to this machine.
     *
     * Resolved per request, so a tunnel pointed at the LAN address keeps working
     * after the DHCP lease or Wi-Fi network changes the server IP.
     *
     * @return list<string>
     */
    protected function localAddresses(): array
    {
        if (! function_exists('net_get_interfaces')) {
            return [];
        }

        $interfaces = @net_get_interfaces();
        if (! is_array($interfaces)) {
            return [];
        }

        $addresses = [];
        foreach ($interfaces as $interface) {
            foreach ($interface['unicast'] ?? [] as $unicast) {
                $address = $unicast['address'] ?? null;
                if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false) {
                    $addresses[] = $address;
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
