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

    /**
     * GET /api/v1/leave-requests — HR: semua pengajuan (filter ?status=pending|approved|rejected|cancelled).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,cancelled'],
        ]);

        $requests = LeaveRequest::with(['employee:id,name,position', 'approver:id,name'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $requests]);
    }

    /**
     * POST /api/v1/leave-requests/{id}/approve — HR menyetujui pengajuan.
     */
    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan berstatus pending yang bisa diproses.'], 422);
        }

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pengajuan disetujui.',
            'data' => $leaveRequest->fresh(['employee:id,name,position', 'approver:id,name']),
        ]);
    }

    /**
     * POST /api/v1/leave-requests/{id}/reject — HR menolak pengajuan (catatan wajib).
     */
    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan berstatus pending yang bisa diproses.'], 422);
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_notes' => $validated['notes'],
        ]);

        return response()->json([
            'message' => 'Pengajuan ditolak.',
            'data' => $leaveRequest->fresh(['employee:id,name,position', 'approver:id,name']),
        ]);
    }

    /**
     * GET /api/v1/leave-requests/stats — ringkasan pengajuan untuk widget HR.
     */
    public function stats(Request $request): JsonResponse
    {
        $pending = LeaveRequest::where('status', 'pending')->count();
        $approved = LeaveRequest::where('status', 'approved')->count();
        $rejected = LeaveRequest::where('status', 'rejected')->count();
        $cancelled = LeaveRequest::where('status', 'cancelled')->count();

        return response()->json([
            'data' => [
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'cancelled' => $cancelled,
                'total' => $pending + $approved + $rejected + $cancelled,
            ],
        ]);
    }
}
