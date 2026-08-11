<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    /**
     * POST /api/v1/attendance/clock-in — absen masuk (validasi radius GPS, waktu dari server).
     */
    public function clockIn(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'selfie_photo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $attendance = $this->attendance->clockIn($employee, $validated);

            return response()->json([
                'message' => 'Clock in berhasil.',
                'data' => $attendance->load('workLocation'),
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/attendance/clock-out — absen pulang.
     */
    public function clockOut(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'selfie_photo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $attendance = $this->attendance->clockOut($employee, $validated);

            return response()->json([
                'message' => 'Clock out berhasil.',
                'data' => $attendance->load('workLocation'),
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/attendance/me — riwayat absen karyawan sendiri (opsional ?date=YYYY-MM-DD).
     */
    public function me(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'data' => $this->attendance->myHistory($this->employee($request), $validated['date'] ?? null),
        ]);
    }

    /**
     * Dipastikan instance Employee oleh middleware 'employee'.
     */
    private function employee(Request $request): Employee
    {
        return $request->user();
    }
}
