<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin-only', fn (User $user) => (bool) $user->isAdmin());
        Gate::define('teacher-or-admin', fn (User $user) => (bool) ($user->isAdmin() || $user->role === 'guru' || (! $user->isStudent())));

        $this->configureQuizRateLimiting();
        $this->recordLoginActivity();
    }

    /**
     * Logins and logouts appear in the activity report with their device, browser and time.
     */
    protected function recordLoginActivity(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(ActivityRecorder::class)->record(ActivityLog::LOGIN, request(), $event->user, meta: ['remember' => $event->remember]);
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(ActivityRecorder::class)->record(ActivityLog::LOGOUT, request(), $event->user);
            }
        });
    }

    /**
     * Rate limits for the public quiz endpoints.
     *
     * Limits are applied per student instead of per IP address: behind an Expose tunnel or
     * a NAT router a whole class shares one IP, and an IP based limit would block students
     * who did nothing wrong. Guests are identified by the encrypted respondent cookie the
     * server issues (it cannot be forged or rotated per request like a form field), and a
     * per-IP ceiling still protects the server against a single misbehaving device.
     */
    protected function configureQuizRateLimiting(): void
    {
        $respondentKey = function (Request $request): string {
            if ($request->user()) {
                return 'user:'.$request->user()->getAuthIdentifier();
            }

            $identifier = $request->cookie('alsen_resp_id');

            return is_string($identifier) && $identifier !== ''
                ? 'respondent:'.$identifier
                : 'ip:'.$request->ip();
        };

        RateLimiter::for('quiz-submissions', fn (Request $request): array => [
            Limit::perMinute(20)->by($respondentKey($request)),
            Limit::perMinute(1000)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('quiz-activity', fn (Request $request): array => [
            Limit::perMinute(120)->by($respondentKey($request)),
            Limit::perMinute(5000)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('quiz-unlock', fn (Request $request): array => [
            Limit::perMinute(5)->by($respondentKey($request)),
            Limit::perMinute(300)->by('ip:'.$request->ip()),
        ]);
    }
}
