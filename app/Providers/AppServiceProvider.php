<?php

namespace App\Providers;

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
        // Tenant domain yang tidak dikenal → 404 (bukan 500)
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::$onFail = function () {
            abort(404);
        };
    }
}
