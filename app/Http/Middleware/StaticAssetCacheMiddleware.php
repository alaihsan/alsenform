<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaticAssetCacheMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $path = $request->path();

        // If request is for immutable build assets, fonts, or media
        if (str_starts_with($path, 'build/') || str_starts_with($path, 'fonts/')) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        } elseif (str_starts_with($path, 'storage/media/')) {
            // Media images can be cached by browsers for 7 days
            $response->headers->set('Cache-Control', 'public, max-age=604800');
        }

        return $response;
    }
}
