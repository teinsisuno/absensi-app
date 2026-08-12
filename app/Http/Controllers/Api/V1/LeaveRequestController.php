<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pengajuan izin/cuti/sakit dari sisi karyawan (PWA mobile).
 * Approval dipegang admin/HR (web) — endpoint karyawan hanya: lihat milik sendiri, buat, batalkan.
 */
class LeaveRequestController extends Controller
{
    /**
     * GET /api/v1/leave-requests/me — daftar pengajuan karyawan sendiri (terbaru dulu).
     */
    public function myRequests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        $requests = $this->employee($request)->leaveRequests()
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $requests]);
    }

    /**
     * POST /api/v1/leave-requests — buat pengajuan baru (default pending).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:izin,cuti,sakit'],
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'string', 'max:255'],
        ]);

        $leave = $this->employee($request)->leaveRequests()->create([
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'attachment' => $validated['attachment'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pengajuan berhasil dikirim dan menunggu persetujuan.',
            'data' => $leave,
        ], 201);
    }

    /**
     * POST /api/v1/leave-requests/{id}/cancel — batalkan pengajuan yang masih pending.
     */
    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $this->employee($request);

        // Hanya bisa membatalkan milik sendiri
        if ((int) $leaveRequest->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Pengajuan ini bukan milik kamu.'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan berstatus pending yang bisa dibatalkan.'], 422);
        }

        $leaveRequest->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Pengajuan dibatalkan.', 'data' => $leaveRequest->fresh()]);
    }

    private function employee(Request $request): Employee
    {
        return $request->user()->employee;
    }
}
