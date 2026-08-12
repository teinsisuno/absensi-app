<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use App\Services\InviteCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class InviteCodeController extends Controller
{
    public function __construct(private readonly InviteCodeService $inviteCode)
    {
    }

    /**
     * GET /api/v1/invite-codes — daftar kode unik beserta pemakainya.
     */
    public function index(): JsonResponse
    {
        $codes = InviteCode::with(['employee:id,name', 'creator:id,name', 'usedByUser:id,name'])
            ->latest()
            ->get();

        return response()->json(['data' => $codes]);
    }

    /**
     * POST /api/v1/invite-codes — generate kode unik untuk karyawan (ditampilkan sekali ke HR).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
        ]);

        try {
            $employee = \App\Models\Employee::findOrFail($validated['employee_id']);
            $invite = $this->inviteCode->generate($employee, $request->user());

            return response()->json([
                'message' => 'Kode unik dibuat. Bagikan ke karyawan — hanya tampil sekali.',
                'data' => [
                    'id' => $invite->id,
                    'employee_id' => $invite->employee_id,
                    'code' => $invite->code,
                    'expires_at' => $invite->expires_at,
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
