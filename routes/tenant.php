<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\WorkLocationController;
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
    Route::post('/auth/admin-login', [AuthController::class, 'adminLogin']);
    Route::post('/auth/employee-login', [AuthController::class, 'employeeLogin']);

    // Auth terproteksi
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    // Admin (owner/admin SSO) — kelola karyawan, PIN, lokasi kerja
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::apiResource('/employees', EmployeeController::class)->except(['show']);
        Route::post('/employees/{employee}/reset-pin', [EmployeeController::class, 'resetPin']);
        Route::apiResource('/work-locations', WorkLocationController::class)->except(['show']);
    });

    // Karyawan (login PIN) — absen & riwayat sendiri
    Route::middleware(['auth:sanctum', 'employee'])->group(function () {
        Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('/attendance/me', [AttendanceController::class, 'me']);
    });
});
