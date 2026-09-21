<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
    }
}
