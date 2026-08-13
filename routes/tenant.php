<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdminAttendanceController;
use App\Http\Controllers\Api\V1\AdminDashboardController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EmployeeGroupController;
use App\Http\Controllers\Api\V1\EmployeeSubmoduleController;
use App\Http\Controllers\Api\V1\FaceController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\InviteCodeController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\OvertimeRequestController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ScheduleSnapshotController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\VisitController;
use App\Http\Controllers\Api\V1\WebauthnController;
use App\Http\Controllers\Api\V1\WorkingCalendarController;
use App\Http\Controllers\Api\V1\WorkLocationController;
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

    // WebAuthn (passkey/biometrik) — login options & login publik (userless)
    Route::post('/auth/webauthn/login/options', [WebauthnController::class, 'loginOptions']);
    Route::post('/auth/webauthn/login', [WebauthnController::class, 'login']);

    // Auth terproteksi — user yang baru daftar / sedang setup
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/set-pin', [AuthController::class, 'setPin']);
        Route::post('/auth/verify-invite', [AuthController::class, 'verifyInvite']);
        Route::post('/auth/link-employee', [AuthController::class, 'linkEmployee']);

        // WebAuthn — pendaftaran biometrik (wajib login PIN dulu)
        Route::post('/auth/webauthn/register/options', [WebauthnController::class, 'registerOptions']);
        Route::post('/auth/webauthn/register', [WebauthnController::class, 'register']);
        Route::get('/auth/webauthn/keys', [WebauthnController::class, 'keys']);
        Route::delete('/auth/webauthn/keys/{id}', [WebauthnController::class, 'destroyKey']);
    });

    // Admin (superadmin/HR) — kelola karyawan, kode unik, lokasi kerja
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::apiResource('/employees', EmployeeController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        // Submodule data karyawan (detail 1:1, bank, keluarga, kontrak, dokumen)
        Route::put('/employees/{employee}/detail', [EmployeeSubmoduleController::class, 'updateDetail']);
        Route::get('/employees/{employee}/banks', [EmployeeSubmoduleController::class, 'indexBanks']);
        Route::post('/employees/{employee}/banks', [EmployeeSubmoduleController::class, 'storeBank']);
        Route::put('/employees/{employee}/banks/{bank}', [EmployeeSubmoduleController::class, 'updateBank']);
        Route::delete('/employees/{employee}/banks/{bank}', [EmployeeSubmoduleController::class, 'destroyBank']);
        Route::get('/employees/{employee}/families', [EmployeeSubmoduleController::class, 'indexFamilies']);
        Route::post('/employees/{employee}/families', [EmployeeSubmoduleController::class, 'storeFamily']);
        Route::put('/employees/{employee}/families/{family}', [EmployeeSubmoduleController::class, 'updateFamily']);
        Route::delete('/employees/{employee}/families/{family}', [EmployeeSubmoduleController::class, 'destroyFamily']);
        Route::get('/employees/{employee}/contracts', [EmployeeSubmoduleController::class, 'indexContracts']);
        Route::post('/employees/{employee}/contracts', [EmployeeSubmoduleController::class, 'storeContract']);
        Route::put('/employees/{employee}/contracts/{contract}', [EmployeeSubmoduleController::class, 'updateContract']);
        Route::delete('/employees/{employee}/contracts/{contract}', [EmployeeSubmoduleController::class, 'destroyContract']);
        Route::get('/employees/{employee}/documents', [EmployeeSubmoduleController::class, 'indexDocuments']);
        Route::post('/employees/{employee}/documents', [EmployeeSubmoduleController::class, 'storeDocument']);
        Route::put('/employees/{employee}/documents/{document}', [EmployeeSubmoduleController::class, 'updateDocument']);
        Route::delete('/employees/{employee}/documents/{document}', [EmployeeSubmoduleController::class, 'destroyDocument']);
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

        // Rekap absensi karyawan (roster per tanggal + detail harian)
        Route::get('/attendance/roster', [AdminAttendanceController::class, 'roster']);
        Route::get('/attendance/roster/{employee}', [AdminAttendanceController::class, 'detail']);
        Route::get('/attendance/export', [AdminAttendanceController::class, 'export']);

        // Approval pengajuan izin/cuti/sakit (HR)
        Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
        Route::get('/leave-requests/stats', [LeaveRequestController::class, 'stats']);
        Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
        Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);

        // Overtime management (HR)
        Route::get('/overtime-requests', [OvertimeRequestController::class, 'index']);
        Route::get('/overtime-requests/stats', [OvertimeRequestController::class, 'stats']);
        Route::post('/overtime-requests/{overtimeRequest}/approve', [OvertimeRequestController::class, 'approve']);
        Route::post('/overtime-requests/{overtimeRequest}/reject', [OvertimeRequestController::class, 'reject']);

        // Kunjungan lapangan (admin lihat semua)
        Route::get('/visits', [VisitController::class, 'index']);
        Route::get('/visits/{visit}', [VisitController::class, 'show'])->whereNumber('visit');

        // Tugas (admin: kelola semua)
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::put('/tasks/{task}', [TaskController::class, 'update']);
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

        // Pengumuman (admin: kelola semua termasuk draft)
        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

        // Pengaturan tenant
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::post('/settings/whatsapp/test', [SettingsController::class, 'testWhatsApp']);
        Route::get('/settings/whatsapp/status', [SettingsController::class, 'whatsappStatus']);
        Route::get('/settings/whatsapp/qr', [SettingsController::class, 'whatsappQr']);
        Route::post('/settings/whatsapp/restart', [SettingsController::class, 'whatsappRestart']);
    });

    // Pengumuman — semua user login (bukan cuma admin) boleh baca yang sudah publish
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::get('/announcements/latest', [AnnouncementController::class, 'latest']);
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);
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

        // Overtime karyawan
        Route::get('/overtime-requests/me', [OvertimeRequestController::class, 'myRequests']);
        Route::post('/overtime-requests', [OvertimeRequestController::class, 'store']);
        Route::post('/overtime-requests/{overtimeRequest}/cancel', [OvertimeRequestController::class, 'cancel']);

        // Kunjungan lapangan (karyawan)
        Route::get('/visits/me', [VisitController::class, 'myVisits']);
        Route::post('/visits', [VisitController::class, 'store']);

        // Tugas (karyawan: lihat & update status)
        Route::get('/tasks/me', [TaskController::class, 'myTasks']);
        Route::put('/tasks/{task}/status', [TaskController::class, 'updateStatus']);

        // Profil & dokumen karyawan
        Route::get('/me', [ProfileController::class, 'me']);
        Route::put('/me', [ProfileController::class, 'updateProfile']);
        Route::get('/me/documents', [ProfileController::class, 'documents']);

        // Face recognition (enroll/verify/status)
        Route::post('/face/enroll', [FaceController::class, 'enroll']);
        Route::post('/face/verify', [FaceController::class, 'verify']);
        Route::get('/face/status', [FaceController::class, 'status']);

        // Jadwal sendiri (calendar PWA)
        Route::get('/schedule-snapshots/me', [ScheduleSnapshotController::class, 'mySchedule']);

        // Group milik user (supervisor: yang dipimpin; karyawan: tempat dia bergabung)
        Route::get('/groups/mine', [EmployeeGroupController::class, 'mine']);
    });
});
