<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
| Semua route di bawah berjalan dalam konteks tenant (DB tenant_absensi_{slug}).
| Subdomain {slug}-absensi.megakomsel.com → InitializeTenancyByDomain.
*/

// Halaman web tenant (nanti: landing sederhana / redirect ke frontend Nuxt)
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return 'Absensi API — tenant '.tenant('id');
    });
});

// API tenant (pakai grup api → tanpa CSRF, ada throttle, response JSON otomatis)
Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api/v1')->group(function () {
    // Auth publik (dalam konteks tenant)
    Route::post('/auth/sso', [AuthController::class, 'sso']);
    Route::post('/auth/employee-login', [AuthController::class, 'employeeLogin']);

    // Auth terproteksi
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });
});
