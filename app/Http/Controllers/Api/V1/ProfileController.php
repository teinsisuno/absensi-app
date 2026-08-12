<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profil karyawan (PWA) — biodata dari employee + detail + ringkasan dokumen.
 */
class ProfileController extends Controller
{
    /**
     * GET /api/v1/me — karyawan: employee + detail + dokumen summary.
     */
    public function me(Request $request): JsonResponse
    {
        $employee = $this->employee($request)->load([
            'detail', 'workLocation', 'shift', 'groups', 'supervisor:id,name',
        ]);

        return response()->json([
            'data' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'photo' => $employee->photo,
                'position' => $employee->position,
                'mobile_role' => $employee->mobile_role,
                'email' => $request->user()->email,
                'user_name' => $request->user()->name,
                'work_location' => $employee->workLocation,
                'shift' => $employee->shift,
                'groups' => $employee->groups,
                'supervisor' => $employee->supervisor,
                'detail' => $employee->detail,
                'documents_count' => $employee->documents()->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/me/documents — karyawan: daftar dokumen miliknya.
     */
    public function documents(Request $request): JsonResponse
    {
        $documents = $this->employee($request)->documents()->orderByDesc('created_at')->get();

        return response()->json(['data' => $documents]);
    }

    /**
     * PUT /api/v1/me — karyawan: update biodata dasar (detail 1:1).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nik' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:L,P'],
            'religion' => ['nullable', 'string', 'max:50'],
            'marital_status' => ['nullable', 'string', 'max:20'],
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'date_format:Y-m-d'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $employee = $this->employee($request);

        EmployeeDetail::updateOrCreate(
            ['employee_id' => $employee->id],
            array_filter($validated, fn ($v) => $v !== null)
        );

        return response()->json([
            'message' => 'Profil diperbarui.',
            'data' => $employee->fresh('detail'),
        ]);
    }

    private function employee(Request $request): Employee
    {
        return $request->user()->employee;
    }
}
