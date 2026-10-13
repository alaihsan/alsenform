<?php

namespace App\Http\Controllers;

use App\Support\UserAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * "Cek Koneksi": measured from the device that is slow, so a teacher can tell a slow network (or a
 * proxy, VPN or antivirus on the device) from a busy server. Open to everyone, also before login.
 */
class ConnectionCheckController extends Controller
{
    /**
     * Size of the test download: large enough to measure the speed, small enough for a class.
     */
    public const DOWNLOAD_BYTES = 1024 * 1024;

    public function page(Request $request, UserAgent $userAgent): InertiaResponse
    {
        $device = $userAgent->parse($request->userAgent(), $userAgent->hintsFrom($request));

        return Inertia::render('ConnectionCheck', [
            'client' => [
                'ip' => $request->ip(),
                // The request came through a proxy on the way (a school proxy, an antivirus web shield).
                'viaProxy' => $request->headers->has('Via') || $request->headers->has('X-Forwarded-For'),
                'device' => collect([$device['device'], $device['os'], $device['browser']])->filter()->implode(' · '),
            ],
            'pingUrl' => route('connection-check.ping'),
            'downloadUrl' => route('connection-check.download'),
        ]);
    }

    /**
     * A tiny answer, timed: the Server-Timing header tells how much of the wait was the server.
     */
    public function ping(): JsonResponse
    {
        return response()
            ->json(['ok' => true])
            ->header('Cache-Control', 'no-store')
            ->header('Server-Timing', 'app;dur='.$this->serverMilliseconds());
    }

    /**
     * Random bytes (they cannot be compressed) to measure the download speed.
     */
    public function download(): Response
    {
        return response(random_bytes(self::DOWNLOAD_BYTES), 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store',
            'Server-Timing' => 'app;dur='.$this->serverMilliseconds(),
        ]);
    }

    protected function serverMilliseconds(): string
    {
        $start = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        return number_format((microtime(true) - $start) * 1000, 1, '.', '');
    }
}
