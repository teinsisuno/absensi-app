<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeeAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeAuthService $employeeAuth)
    {
    }

    /**
     * GET /api/v1/employees — daftar karyawan (opsional ?status=active|inactive).
     */
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::with(['workLocation', 'shift'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->query('status'))
            )
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $employees]);
    }

    /**
     * POST /api/v1/employees — tambah karyawan; sistem generate PIN unik (ditampilkan sekali).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'work_location_id' => ['nullable', 'integer', Rule::exists('work_locations', 'id')],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        try {
            $pin = $this->employeeAuth->generateUniquePin();
            $employee = Employee::create([
                ...$validated,
                'pin_hash' => Hash::make($pin),
            ]);

            return response()->json([
                'message' => 'Karyawan dibuat. PIN hanya ditampilkan sekali.',
                'data' => $employee->fresh(['workLocation', 'shift']),
                'pin' => $pin,
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/v1/employees/{id} — edit karyawan (PIN tidak berubah).
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'work_location_id' => ['nullable', 'integer', Rule::exists('work_locations', 'id')],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $employee->update($validated);

        return response()->json(['data' => $employee->fresh(['workLocation', 'shift'])]);
    }

    /**
     * POST /api/v1/employees/{id}/reset-pin — generate PIN baru (ditampilkan sekali).
     */
    public function resetPin(Employee $employee): JsonResponse
    {
        $pin = $this->employeeAuth->resetPin($employee);

        return response()->json([
            'message' => 'PIN berhasil di-reset. PIN hanya ditampilkan sekali.',
            'pin' => $pin,
        ]);
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
