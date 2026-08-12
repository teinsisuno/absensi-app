<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeGroupController extends Controller
{
    /**
     * GET /api/v1/groups — daftar group + jumlah anggota + kepala group.
     */
    public function index(): JsonResponse
    {
        $groups = EmployeeGroup::withCount('members')
            ->with('supervisor:id,name')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $groups]);
    }

    /**
     * POST /api/v1/groups — tambah group karyawan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'supervisor_id' => ['sometimes', 'nullable', 'exists:employees,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $group = EmployeeGroup::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'supervisor_id' => $validated['supervisor_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $group->loadCount('members')->load('supervisor:id,name')], 201);
    }

    /**
     * GET /api/v1/groups/{id} — detail group + daftar anggota (buat modal edit).
     */
    public function show(EmployeeGroup $group): JsonResponse
    {
        return response()->json([
            'data' => $group->loadCount('members')->load(['supervisor:id,name', 'members:id,name,position']),
        ]);
    }

    /**
     * PUT /api/v1/groups/{id} — edit group + sinkronisasi anggota (member_ids).
     */
    public function update(Request $request, EmployeeGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'supervisor_id' => ['sometimes', 'nullable', 'exists:employees,id'],
            'is_active' => ['sometimes', 'boolean'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $group->update([
            'name' => $validated['name'] ?? $group->name,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $group->description,
            'supervisor_id' => array_key_exists('supervisor_id', $validated) ? $validated['supervisor_id'] : $group->supervisor_id,
            'is_active' => $validated['is_active'] ?? $group->is_active,
        ]);

        if (isset($validated['member_ids'])) {
            $group->members()->sync($validated['member_ids']);
        }

        return response()->json([
            'data' => $group->fresh()->loadCount('members')->load('members:id,name'),
        ]);
    }

    /**
     * DELETE /api/v1/groups/{id} — hapus group (pivot ikut terhapus).
     */
    public function destroy(EmployeeGroup $group): JsonResponse
    {
        $group->delete();

        return response()->json(['message' => 'Group dihapus.']);
    }

    /**
     * GET /api/v1/groups/mine — group milik user:
     * - supervisor → group yang dia pimpin (supervisor_id = employee user)
     * - karyawan biasa → group tempat dia jadi anggota
     */
    public function mine(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        $groups = EmployeeGroup::withCount('members')
            ->with(['supervisor:id,name'])
            ->where(function ($q) use ($employee) {
                $q->where('supervisor_id', $employee->id)
                    ->orWhereHas('members', fn ($m) => $m->where('employees.id', $employee->id));
            })
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $groups]);
    }

    /**
     * GET /api/v1/groups/available-employees — daftar karyawan untuk dipilih jadi anggota.
     */
    public function availableEmployees(): JsonResponse
    {
        $employees = Employee::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'position']);

        return response()->json(['data' => $employees]);
    }
}
