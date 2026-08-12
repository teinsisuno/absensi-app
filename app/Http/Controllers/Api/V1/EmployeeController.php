<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class EmployeeController extends Controller
{
    /**
     * GET /api/v1/employees — daftar karyawan (opsional ?status=active|inactive).
     */
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::with(['workLocation', 'shift', 'user:id,name,email'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->query('status'))
            )
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $employees]);
    }

    /**
     * GET /api/v1/employees/{id} — detail karyawan + submodules.
     * Eager load: detail, banks, documents, families, contracts, faceTemplate, groups, workLocation, shift.
     */
    public function show(Employee $employee): JsonResponse
    {
        $employee->load([
            'detail', 'banks', 'documents', 'families', 'contracts',
            'faceTemplate', 'groups', 'workLocation', 'shift',
            'user:id,name,email',
        ]);

        return response()->json(['data' => $employee]);
    }

    /**
     * POST /api/v1/employees — tambah karyawan (data kepegawaian).
     * Link ke akun user dilakukan via kode unik, bukan di sini.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'mobile_role' => ['sometimes', Rule::in(['karyawan', 'supervisor', 'management'])],
            'work_location_id' => ['nullable', 'integer', Rule::exists('work_locations', 'id')],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        try {
            $employee = Employee::create($validated);

            return response()->json([
                'message' => 'Karyawan dibuat. Generate kode unik untuk link akun karyawan.',
                'data' => $employee->fresh(['workLocation', 'shift']),
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/v1/employees/{id} — edit karyawan.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'mobile_role' => ['sometimes', Rule::in(['karyawan', 'supervisor', 'management'])],
            'work_location_id' => ['nullable', 'integer', Rule::exists('work_locations', 'id')],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $employee->update($validated);

        return response()->json(['data' => $employee->fresh(['workLocation', 'shift'])]);

    }

    /**
     * DELETE /api/v1/employees/{id} — nonaktifkan karyawan (soft, riwayat absen tetap aman).
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $employee->update(['status' => 'inactive']);

        return response()->json(['message' => 'Karyawan dinonaktifkan.']);
    }
}
