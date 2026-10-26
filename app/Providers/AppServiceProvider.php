<?php

namespace App\Providers;

use Database\Seeders\RolesAndPermissionsSeeder;
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
        // Le super-admin passe toutes les autorisations.
        Gate::before(fn ($user) => $user->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN) ?: null);
    }
}
