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
        Gate::define('manage-users', fn (User $user): bool => $user->is_active && $user->isPrimaryAdministrator());
        Gate::define('manage-collaborators', fn (User $user): bool => $user->is_active && $user->isAdministrator());
        Gate::define('manage-job-roles', fn (User $user): bool => $user->is_active && $user->isAdministrator());
        Gate::define('manage-control-periods', fn (User $user): bool => $user->is_active && $user->isAdministrator());
        Gate::define('import-biometric-data', fn (User $user): bool => $user->is_active && $user->isAdministrator());
        Gate::define('manage-attendance-corrections', fn (User $user): bool => $user->is_active && $user->isAdministrator());
        Gate::define('calculate-attendance', fn (User $user): bool => $user->is_active && $user->isAdministrator());
    }
}
