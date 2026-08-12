<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pengajuan lembur — sisi karyawan (buat, lihat, batalkan) & sisi HR (index, approve, reject, stats).
 */
class OvertimeRequestController extends Controller
{
    /**
     * GET /api/v1/overtime-requests — HR: semua pengajuan (filter ?status=).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        $requests = OvertimeRequest::with(['employee:id,name,position', 'approver:id,name'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $requests]);
    }

    /**
     * GET /api/v1/overtime-requests/me — karyawan: riwayat lembur sendiri.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $requests = $this->employee($request)->overtimeRequests()
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $requests]);
    }

    /**
     * POST /api/v1/overtime-requests — karyawan: buat pengajuan lembur (default pending).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $overtime = $this->employee($request)->overtimeRequests()->create([
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pengajuan lembur terkirim dan menunggu persetujuan.',
            'data' => $overtime,
        ], 201);
    }

    /**
     * POST /api/v1/overtime-requests/{id}/cancel — karyawan: batalkan yang masih pending.
     */
    public function cancel(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $employee = $this->employee($request);

        if ((int) $overtimeRequest->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Pengajuan ini bukan milik kamu.'], 403);
        }

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan berstatus pending yang bisa dibatalkan.'], 422);
        }

        $overtimeRequest->update(['status' => 'rejected']);

        return response()->json(['message' => 'Pengajuan lembur dibatalkan.', 'data' => $overtimeRequest->fresh()]);
    }

    /**
     * POST /api/v1/overtime-requests/{id}/approve — HR menyetujui.
     */
    public function approve(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan berstatus pending yang bisa diproses.'], 422);
        }

        $overtimeRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pengajuan lembur disetujui.',
            'data' => $overtimeRequest->fresh(['employee:id,name,position', 'approver:id,name']),
        ]);
    }

    /**
     * POST /api/v1/overtime-requests/{id}/reject — HR menolak (catatan wajib).
     */
    public function reject(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan berstatus pending yang bisa diproses.'], 422);
        }

        $overtimeRequest->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_notes' => $validated['notes'],
        ]);

        return response()->json([
            'message' => 'Pengajuan lembur ditolak.',
            'data' => $overtimeRequest->fresh(['employee:id,name,position', 'approver:id,name']),
        ]);
    }

    /**
     * GET /api/v1/overtime-requests/stats — ringkasan untuk widget HR.
     */
    public function stats(Request $request): JsonResponse
    {
        $pending = OvertimeRequest::where('status', 'pending')->count();
        $approved = OvertimeRequest::where('status', 'approved')->count();
        $rejected = OvertimeRequest::where('status', 'rejected')->count();

        return response()->json([
            'data' => [
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'total' => $pending + $approved + $rejected,
            ],
        ]);
    }

    private function employee(Request $request): Employee
    {
        return $request->user()->employee;
    }
}
