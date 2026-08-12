<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Services\FaceRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly FaceRecognitionService $face,
    ) {}

    /**
     * POST /api/v1/attendance/clock-in — absen masuk (validasi radius GPS, waktu dari server).
     */
    public function clockIn(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'selfie_photo' => ['nullable', 'string'],
            'face_descriptor' => ['nullable', 'string'],
            'force' => ['sometimes', 'boolean'],
        ]);

        try {
            $data = $this->withFaceVerification($employee, $validated);

            $attendance = $this->attendance->clockIn($employee, $data, (bool) ($validated['force'] ?? false));

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
            'selfie_photo' => ['nullable', 'string'],
            'face_descriptor' => ['nullable', 'string'],
            'force' => ['sometimes', 'boolean'],
        ]);

        try {
            $data = $this->withFaceVerification($employee, $validated);

            $attendance = $this->attendance->clockOut($employee, $data, (bool) ($validated['force'] ?? false));

            return response()->json([
                'message' => 'Clock out berhasil.',
                'data' => $attendance->load('workLocation'),
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Kalau face_descriptor dikirim, wajib cocok dengan template terdaftar
     * sebelum record absen dibuat. Hasilnya disimpan di kolom face_*.
     */
    private function withFaceVerification(Employee $employee, array $data): array
    {
        if (empty($data['face_descriptor'])) {
            return $data;
        }

        $result = $this->face->verify($employee, $data['face_descriptor']);

        if (! $result['match']) {
            throw new \InvalidArgumentException('Verifikasi wajah gagal. Wajah tidak cocok dengan template terdaftar.');
        }

        $data['face_verified'] = true;
        $data['face_mode'] = $result['mode'];
        $data['face_confidence'] = $result['confidence'];

        return $data;
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
     * Dipastikan user ter-link ke karyawan aktif oleh middleware 'employee'.
     */
    private function employee(Request $request): Employee
    {
        return $request->user()->employee;
    }
}
