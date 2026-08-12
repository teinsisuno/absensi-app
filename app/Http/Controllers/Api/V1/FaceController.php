<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\FaceRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Enroll, verifikasi & cek status wajah karyawan.
 * Semua route di grup middleware `employee` — butuh user ter-link karyawan aktif.
 */
class FaceController extends Controller
{
    public function __construct(private readonly FaceRecognitionService $face) {}

    /**
     * POST /api/v1/face/enroll — simpan/replace template wajah karyawan.
     * Body: { template: "JSON array embedding", mode?: "client"|"server" }
     */
    public function enroll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template' => ['required', 'string'],
            'mode' => ['nullable', 'in:client,server'],
        ]);

        try {
            $template = $this->face->enroll(
                $this->employee($request),
                $validated['template'],
                $validated['mode'] ?? 'server'
            );

            return response()->json([
                'message' => 'Wajah berhasil didaftarkan.',
                'data' => ['mode' => $template->mode, 'employee_id' => $template->employee_id],
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/face/verify — cocokkan descriptor wajah saat absen.
     * Body: { descriptor: "JSON array embedding" }
     * Response: { match, confidence, distance, mode }
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'descriptor' => ['required', 'string'],
        ]);

        try {
            $result = $this->face->verify($this->employee($request), $validated['descriptor']);

            if (! $result['match']) {
                return response()->json([
                    'message' => 'Wajah tidak cocok dengan template terdaftar.',
                    'data' => $result,
                ], 422);
            }

            return response()->json([
                'message' => 'Verifikasi wajah berhasil.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/face/status — cek karyawan sudah enroll wajah atau belum.
     */
    public function status(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        return response()->json([
            'data' => [
                'enrolled' => $this->face->isEnrolled($employee),
            ],
        ]);
    }

    private function employee(Request $request): Employee
    {
        return $request->user()->employee;
    }
}
