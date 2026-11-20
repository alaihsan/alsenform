<?php

namespace App\Providers;

use App\Database\MariaDbConnection;
use App\Database\MySqlConnection;
use App\Database\PostgresConnection;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Addons;
use App\Support\ActivityRecorder;
use App\Support\ErrorPage;
use App\Support\ServerErrorRecorder;
use Closure;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request: the error page remembers whether it just updated the database.
        $this->app->scoped(ErrorPage::class);

        // Which add-ons are installed is looked up once per request.
        $this->app->scoped(Addons::class);

        // The reference code of a server error, from its report to its error page.
        $this->app->scoped(ServerErrorRecorder::class);

        $this->retryDeadlockedStatements();
    }

    /**
     * A statement the database cancelled to break a deadlock, as happens when a whole class polls
     * its exam in the same second, is run again instead of ending in an error.
     */
    protected function retryDeadlockedStatements(): void
    {
        $connections = ['mysql' => MySqlConnection::class, 'mariadb' => MariaDbConnection::class, 'pgsql' => PostgresConnection::class];

        foreach ($connections as $driver => $connectionClass) {
            Connection::resolverFor($driver, fn (PDO|Closure $pdo, string $database, string $prefix, array $config): Connection => new $connectionClass($pdo, $database, $prefix, $config));
        }
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
        $this->writePasswordResetEmailsInIndonesian();
    }

    /**
     * The email with the link to reset a password, in Indonesian like the rest of the app.
     */
    protected function writePasswordResetEmailsInIndonesian(): void
    {
        ResetPassword::toMailUsing(function (CanResetPassword $user, string $token): MailMessage {
            $url = url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false));

            return (new MailMessage)
                ->subject('Atur ulang kata sandi '.config('app.name'))
                ->markdown('mail.reset-password', [
                    'name' => $user instanceof User ? $user->name : $user->getEmailForPasswordReset(),
                    'url' => $url,
                    'minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ]);
        });
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

        // Cek Koneksi: a test is 20 pings and 2 downloads; each counted on its own, per device.
        RateLimiter::for('connection-check-ping', fn (Request $request): Limit => Limit::perMinute(240)->by('connection-check-ping:'.$request->ip()));
        RateLimiter::for('connection-check-download', fn (Request $request): Limit => Limit::perMinute(30)->by('connection-check-download:'.$request->ip()));

        // Pages that stayed blank: a few per device and minute are plenty for the report.
        RateLimiter::for('page-failures', fn (Request $request): Limit => Limit::perMinute(10)->by('page-failures:'.$request->ip()));

        RateLimiter::for('quiz-unlock', fn (Request $request): array => [
            Limit::perMinute(5)->by($respondentKey($request)),
            Limit::perMinute(300)->by('ip:'.$request->ip()),
        ]);
    }
}
