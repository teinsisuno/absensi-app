<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    /**
     * GET /api/v1/holidays — daftar libur, filter ?working_calendar_id= & ?year=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Holiday::query()
            ->with('workingCalendar:id,name,year')
            ->orderBy('date');

        if ($request->filled('working_calendar_id')) {
            $query->where('working_calendar_id', $request->integer('working_calendar_id'));
        }

        if ($request->filled('year')) {
            $query->whereYear('date', $request->integer('year'));
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * POST /api/v1/holidays — tambah hari libur.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'working_calendar_id' => ['required', 'exists:working_calendars,id'],
            'date' => ['required', 'date'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'in:nasional,company'],
            'is_national_holiday' => ['sometimes', 'boolean'],
            'is_company_holiday' => ['sometimes', 'boolean'],
        ]);

        $type = $validated['type'] ?? 'nasional';

        $holiday = Holiday::create([
            'working_calendar_id' => $validated['working_calendar_id'],
            'date' => $validated['date'],
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'type' => $type,
            'is_national_holiday' => $type === 'nasional',
            'is_company_holiday' => $type === 'company',
        ]);

        return response()->json(['data' => $holiday], 201);
    }

    /**
     * PUT /api/v1/holidays/{id} — edit libur.
     */
    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $validated = $request->validate([
            'working_calendar_id' => ['sometimes', 'required', 'exists:working_calendars,id'],
            'date' => ['sometimes', 'required', 'date'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'in:nasional,company'],
            'is_national_holiday' => ['sometimes', 'boolean'],
            'is_company_holiday' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['type'])) {
            $validated['is_national_holiday'] = $validated['type'] === 'nasional';
            $validated['is_company_holiday'] = $validated['type'] === 'company';
        }

        $holiday->update($validated);

        return response()->json(['data' => $holiday->fresh()]);
    }

    /**
     * DELETE /api/v1/holidays/{id} — hapus libur.
     */
    public function destroy(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return response()->json(['message' => 'Hari libur dihapus.']);
    }
}
