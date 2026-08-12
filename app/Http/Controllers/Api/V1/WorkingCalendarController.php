<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkingCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkingCalendarController extends Controller
{
    /**
     * GET /api/v1/working-calendars — daftar kalender kerja + jumlah libur.
     */
    public function index(): JsonResponse
    {
        $calendars = WorkingCalendar::withCount('holidays')
            ->orderByDesc('year')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $calendars]);
    }

    /**
     * POST /api/v1/working-calendars — tambah kalender kerja tahunan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $calendar = WorkingCalendar::create([
            'name' => $validated['name'],
            'year' => $validated['year'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $calendar], 201);
    }

    /**
     * PUT /api/v1/working-calendars/{id} — edit kalender.
     */
    public function update(Request $request, WorkingCalendar $workingCalendar): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'year' => ['sometimes', 'required', 'integer', 'min:2020', 'max:2100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $workingCalendar->update($validated);

        return response()->json(['data' => $workingCalendar->fresh()]);
    }

    /**
     * DELETE /api/v1/working-calendars/{id} — hapus kalender (libur ikut terhapus).
     */
    public function destroy(WorkingCalendar $workingCalendar): JsonResponse
    {
        $workingCalendar->delete();

        return response()->json(['message' => 'Kalender kerja dihapus.']);
    }
}
