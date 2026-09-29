<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GzipResponseMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Do not compress binary or streamed responses
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        if (! extension_loaded('zlib')) {
            return $response;
        }

        $acceptEncoding = $request->header('Accept-Encoding', '');
        if (! str_contains($acceptEncoding, 'gzip')) {
            return $response;
        }

        if ($response->headers->has('Content-Encoding')) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        $compressible = [
            'text/html',
            'application/json',
            'text/plain',
            'text/css',
            'application/javascript',
            'application/x-javascript',
            'text/javascript',
            'image/svg+xml',
            'application/xml',
        ];

        $shouldCompress = false;
        foreach ($compressible as $type) {
            if (str_starts_with($contentType, $type)) {
                $shouldCompress = true;
                break;
            }
        }

        if (! $shouldCompress) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || strlen($content) < 1024) {
            return $response;
        }

        $compressed = @gzencode($content, 6);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->headers->set('Content-Length', (string) strlen($compressed));

        return $response;
    }
}
