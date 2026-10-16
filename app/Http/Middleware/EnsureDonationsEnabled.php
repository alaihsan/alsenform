<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The donation endpoints only simulate a QRIS payment, so they answer only when switched on
 * (DONATIONS_ENABLED, see config/app.php), before any input is validated.
 */
class EnsureDonationsEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('app.donations'), 404);

        return $next($request);
    }
}
