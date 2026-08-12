<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdminDashboardController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EmployeeGroupController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\InviteCodeController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\ScheduleSnapshotController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\WorkLocationController;
use App\Http\Controllers\Api\V1\WorkingCalendarController;
use App\Http\Controllers\Api\V1\WorkPatternController;
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
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/pin-login', [AuthController::class, 'pinLogin']);
    Route::post('/auth/sso', [AuthController::class, 'sso']);
    Route::post('/auth/admin-login', [AuthController::class, 'adminLogin']);

    // Auth terproteksi — user yang baru daftar / sedang setup
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/set-pin', [AuthController::class, 'setPin']);
        Route::post('/auth/verify-invite', [AuthController::class, 'verifyInvite']);
        Route::post('/auth/link-employee', [AuthController::class, 'linkEmployee']);
    });

    // Admin (superadmin/HR) — kelola karyawan, kode unik, lokasi kerja
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::apiResource('/employees', EmployeeController::class)->except(['show']);
        Route::apiResource('/invite-codes', InviteCodeController::class)->only(['index', 'store']);
        Route::apiResource('/work-locations', WorkLocationController::class)->except(['show']);

        // Group karyawan (route khusus didahulukan sebelum apiResource)
        Route::get('/groups/available-employees', [EmployeeGroupController::class, 'availableEmployees']);
        Route::apiResource('/groups', EmployeeGroupController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        // Dashboard admin
        Route::get('/admin/stats', [AdminDashboardController::class, 'stats']);

        // Kalender kerja & hari libur
        Route::apiResource('/working-calendars', WorkingCalendarController::class)->except(['show']);
        Route::apiResource('/holidays', HolidayController::class)->except(['show']);

        // Pola kerja & shift
        Route::apiResource('/work-patterns', WorkPatternController::class)->except(['show']);
        Route::apiResource('/shifts', ShiftController::class)->except(['show']);

        // Jadwal (snapshot per karyawan per tanggal)
        Route::apiResource('/schedule-snapshots', ScheduleSnapshotController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    // Karyawan (user ter-link) — absen, riwayat, pengajuan, jadwal sendiri
    Route::middleware(['auth:sanctum', 'employee'])->group(function () {
        Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('/attendance/me', [AttendanceController::class, 'me']);

        // Pengajuan izin/cuti/sakit (karyawan)
        Route::get('/leave-requests/me', [LeaveRequestController::class, 'myRequests']);
        Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
        Route::post('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);

        // Jadwal sendiri (calendar PWA)
        Route::get('/schedule-snapshots/me', [ScheduleSnapshotController::class, 'mySchedule']);

        // Group milik user (supervisor: yang dipimpin; karyawan: tempat dia bergabung)
        Route::get('/groups/mine', [EmployeeGroupController::class, 'mine']);
    });
});
