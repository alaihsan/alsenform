<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request. The same answer whether the address has an
     * account or not; only an email the server cannot send is told, with how to connect one.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            Password::sendResetLink(
                $request->only('email')
            );
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withErrors([
                'email' => 'Email belum dapat dikirim dari server. Admin perlu menyambungkan akun Gmail pengirim dengan perintah "php artisan mail:gmail".',
            ]);
        }

        return back()->with('status', 'Jika alamat itu terdaftar, tautan atur ulang kata sandi sudah dikirim. Periksa kotak masuk atau folder Spam.');
    }
}
