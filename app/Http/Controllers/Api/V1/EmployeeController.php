<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class EmployeeController extends Controller
{
    /**
     * GET /api/v1/employees — daftar karyawan (opsional ?status=active|inactive).
     */
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::with(['workLocation', 'shift', 'user:id,name,email', 'detail'])
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
     * POST /api/v1/employees — tambah karyawan (data kepegawaian + detail personal opsional).
     * Link ke akun user dilakukan via kode unik, bukan di sini.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules() + [
            'detail' => ['sometimes', 'array'],
            'detail.nik' => ['nullable', 'string', 'max:50'],
            'detail.gender' => ['nullable', 'in:L,P'],
            'detail.religion' => ['nullable', 'string', 'max:50'],
            'detail.blood_type' => ['nullable', 'string', 'max:10'],
            'detail.marital_status' => ['nullable', 'string', 'max:20'],
            'detail.place_of_birth' => ['nullable', 'string', 'max:100'],
            'detail.date_of_birth' => ['nullable', 'date', 'date_format:Y-m-d'],
            'detail.address' => ['nullable', 'string', 'max:1000'],
            'detail.phone' => ['nullable', 'string', 'max:30'],
            'detail.email' => ['nullable', 'email', 'max:255'],
            'detail.emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'detail.emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'detail.npwp' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $employee = DB::transaction(function () use ($validated) {
                $detail = $validated['detail'] ?? [];
                unset($validated['detail']);

                $employee = Employee::create($validated);

                if (array_filter($detail)) {
                    $employee->detail()->create($detail);
                }

                return $employee;
            });

            return response()->json([
                'message' => 'Karyawan dibuat. Generate kode unik untuk link akun karyawan.',
                'data' => $employee->fresh(['workLocation', 'shift', 'detail']),
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/v1/employees/{id} — edit karyawan (+ detail personal kalau dikirim).
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate($this->rules() + [
            'detail' => ['sometimes', 'array'],
            'detail.nik' => ['nullable', 'string', 'max:50'],
            'detail.gender' => ['nullable', 'in:L,P'],
            'detail.religion' => ['nullable', 'string', 'max:50'],
            'detail.blood_type' => ['nullable', 'string', 'max:10'],
            'detail.marital_status' => ['nullable', 'string', 'max:20'],
            'detail.place_of_birth' => ['nullable', 'string', 'max:100'],
            'detail.date_of_birth' => ['nullable', 'date', 'date_format:Y-m-d'],
            'detail.address' => ['nullable', 'string', 'max:1000'],
            'detail.phone' => ['nullable', 'string', 'max:30'],
            'detail.email' => ['nullable', 'email', 'max:255'],
            'detail.emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'detail.emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'detail.npwp' => ['nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($validated, $employee) {
            $detail = $validated['detail'] ?? [];
            unset($validated['detail']);

            $employee->update($validated);

            if (array_filter($detail)) {
                $employee->detail()->updateOrCreate(['employee_id' => $employee->id], $detail);
            }
        });

        return response()->json(['data' => $employee->fresh(['workLocation', 'shift', 'detail'])]);

    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'mobile_role' => ['sometimes', Rule::in(['karyawan', 'supervisor', 'management'])],
            'work_location_id' => ['nullable', 'integer', Rule::exists('work_locations', 'id')],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
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
