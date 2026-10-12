<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestClientHints
{
    /**
     * The User-Agent Client Hints the activity report reads: Chrome and Edge hide the phone model,
     * the Windows/macOS/Android version and the full browser version from their User-Agent, and
     * send them in these headers when asked. Browsers only send them over HTTPS.
     */
    public const HINTS = 'Sec-CH-UA-Model, Sec-CH-UA-Platform-Version, Sec-CH-UA-Full-Version-List';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Accept-CH')) {
            $response->headers->set('Accept-CH', self::HINTS);
        }

        return $response;
    }
}
