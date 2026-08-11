<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Catatan: TIDAK pakai EnsureFrontendRequestsAreStateful (Sanctum) di grup api.
        // Frontend Nuxt login via Bearer token (Authorization header), bukan cookie session.
        // Sanctum stateful malah bikin request dengan Origin localhost:3000 dianggap SPA →
        // CSRF 419. CORS default allow-all + Bearer token cukup untuk arsitektur ini.

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'employee' => \App\Http\Middleware\EnsureEmployee::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
