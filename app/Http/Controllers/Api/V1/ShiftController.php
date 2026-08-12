<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /**
     * GET /api/v1/shifts — daftar shift, filter ?work_pattern_id=.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shift::query()
            ->with('workPattern:id,code,name')
            ->orderBy('work_hour_start');

        if ($request->filled('work_pattern_id')) {
            $query->where('work_pattern_id', $request->integer('work_pattern_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * POST /api/v1/shifts — tambah shift.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_pattern_id' => ['sometimes', 'nullable', 'exists:work_patterns,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:shifts,code'],
            'work_hour_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'work_hour_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_in_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_in_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'is_overnight' => ['sometimes', 'boolean'],
            'tolerance_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'min_work_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:24'],
            'has_overtime' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $shift = Shift::create([
            'work_pattern_id' => $validated['work_pattern_id'] ?? null,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'work_hour_start' => $validated['work_hour_start'] ?? null,
            'work_hour_end' => $validated['work_hour_end'] ?? null,
            'check_in_start' => $validated['check_in_start'] ?? null,
            'check_in_end' => $validated['check_in_end'] ?? null,
            'check_out_start' => $validated['check_out_start'] ?? null,
            'check_out_end' => $validated['check_out_end'] ?? null,
            'is_overnight' => $validated['is_overnight'] ?? false,
            'tolerance_minutes' => $validated['tolerance_minutes'] ?? 15,
            'min_work_hours' => $validated['min_work_hours'] ?? null,
            'has_overtime' => $validated['has_overtime'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $shift->load('workPattern:id,code,name')], 201);
    }

    /**
     * PUT /api/v1/shifts/{id} — edit shift.
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        $validated = $request->validate([
            'work_pattern_id' => ['sometimes', 'nullable', 'exists:work_patterns,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:shifts,code,'.$shift->id],
            'work_hour_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'work_hour_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_in_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_in_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'is_overnight' => ['sometimes', 'boolean'],
            'tolerance_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'min_work_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:24'],
            'has_overtime' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $shift->update($validated);

        return response()->json(['data' => $shift->fresh()->load('workPattern:id,code,name')]);
    }

    /**
     * DELETE /api/v1/shifts/{id} — hapus shift.
     */
    public function destroy(Shift $shift): JsonResponse
    {
        $shift->delete();

        return response()->json(['message' => 'Shift dihapus.']);
    }
}
