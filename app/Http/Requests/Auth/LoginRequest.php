<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = trim((string) $this->input('identifier'));
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false) {
            $attempt = Auth::attempt([
                'email' => Str::lower($identifier),
                'password' => $this->input('password'),
                fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                    $query->where('role', 'admin')->orWhere('is_admin', true);
                }),
            ], $this->boolean('remember'));
        } else {
            $attempt = Auth::attempt([
                'nip' => $identifier,
                'role' => 'guru',
                'is_admin' => false,
                'password' => $this->input('password'),
            ], $this->boolean('remember'));

            if (! $attempt) {
                $attempt = Auth::attempt([
                    'nis' => $identifier,
                    'is_admin' => false,
                    'password' => $this->input('password'),
                    fn (Builder $query): Builder => $query->whereIn('role', ['siswa', 'murid']),
                ], $this->boolean('remember'));
            }
        }

        if (! $attempt) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'identifier' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('identifier').'|'.$this->ip()));
    }
}
