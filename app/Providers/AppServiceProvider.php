<?php

namespace App\Providers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use LaravelWebauthn\Facades\Webauthn;
use LaravelWebauthn\Services\Webauthn\CreationOptionsFactory;
use LaravelWebauthn\Services\Webauthn\CredentialAssertionValidator;
use LaravelWebauthn\Services\Webauthn\CredentialAttestationValidator;
use LaravelWebauthn\Services\Webauthn\RequestOptionsFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // API-only: pakai route WebAuthn kita sendiri (routes/tenant.php),
        // matikan route bawaan package yang butuh session web guard.
        Webauthn::ignoreRoutes();

        // Challenge WebAuthn harus survive antar request (prepare → browser → validate).
        // CACHE_STORE=array (dev) tidak persist antar request → paksa store 'file'
        // khusus service WebAuthn. Di prod (redis) file store tetap aman & ringan.
        $webauthnClasses = [
            RequestOptionsFactory::class,
            CreationOptionsFactory::class,
            CredentialAssertionValidator::class,
            CredentialAttestationValidator::class,
        ];

        foreach ($webauthnClasses as $class) {
            $this->app->when($class)
                ->needs(CacheRepository::class)
                ->give(fn () => Cache::store('file'));
        }
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
