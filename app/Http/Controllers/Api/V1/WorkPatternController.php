<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkPattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkPatternController extends Controller
{
    /**
     * GET /api/v1/work-patterns — daftar pola kerja.
     */
    public function index(): JsonResponse
    {
        $patterns = WorkPattern::withCount('shifts')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $patterns]);
    }

    /**
     * POST /api/v1/work-patterns — tambah pola kerja.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:work_patterns,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'employee_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'work_day' => ['sometimes', 'integer', 'min:1', 'max:7'],
            'sat_type' => ['sometimes', 'in:off,full,half'],
            'work_day_hours' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'half_day_hours' => ['sometimes', 'integer', 'min:0', 'max:24'],
            'wd_rest_hours' => ['sometimes', 'integer', 'min:0', 'max:24'],
            'hd_rest_hours' => ['sometimes', 'integer', 'min:0', 'max:24'],
            'sun_overtime' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $pattern = WorkPattern::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'employee_type' => $validated['employee_type'] ?? null,
            'work_day' => $validated['work_day'] ?? 5,
            'sat_type' => $validated['sat_type'] ?? 'off',
            'work_day_hours' => $validated['work_day_hours'] ?? 8,
            'half_day_hours' => $validated['half_day_hours'] ?? 4,
            'wd_rest_hours' => $validated['wd_rest_hours'] ?? 1,
            'hd_rest_hours' => $validated['hd_rest_hours'] ?? 0,
            'sun_overtime' => $validated['sun_overtime'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $pattern], 201);
    }

    /**
     * PUT /api/v1/work-patterns/{id} — edit pola kerja.
     */
    public function update(Request $request, WorkPattern $workPattern): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:work_patterns,code,'.$workPattern->id],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'employee_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'work_day' => ['sometimes', 'integer', 'min:1', 'max:7'],
            'sat_type' => ['sometimes', 'in:off,full,half'],
            'work_day_hours' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'half_day_hours' => ['sometimes', 'integer', 'min:0', 'max:24'],
            'wd_rest_hours' => ['sometimes', 'integer', 'min:0', 'max:24'],
            'hd_rest_hours' => ['sometimes', 'integer', 'min:0', 'max:24'],
            'sun_overtime' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $workPattern->update($validated);

        return response()->json(['data' => $workPattern->fresh()]);
    }

    /**
     * DELETE /api/v1/work-patterns/{id} — hapus pola kerja.
     */
    public function destroy(WorkPattern $workPattern): JsonResponse
    {
        $workPattern->delete();

        return response()->json(['message' => 'Pola kerja dihapus.']);
    }
}
