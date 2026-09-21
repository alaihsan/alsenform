<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            $allowedRoutes = [
                'password.edit',
                'password.update',
                'logout',
                'forms.public',
                'forms.responses.store',
                'forms.responses.lock',
                'forms.public.unlock-requests.store',
                'forms.public.unlock-requests.status',
                'forms.public.unlock-verify',
            ];

            $currentRouteName = $request->route()?->getName();

            if (! in_array($currentRouteName, $allowedRoutes) && ! $request->is('logout', 'settings/password')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Anda wajib mengganti password akun sebelum melanjutkan.',
                        'must_change_password' => true,
                    ], 403);
                }

                return redirect()->route('password.edit')->with('warning', 'Demi keamanan, Anda wajib mengganti password default sebelum dapat menggunakan aplikasi.');
            }
        }

        return $next($request);
    }
}
