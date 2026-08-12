<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kunjungan lapangan — karyawan catat kunjungan (selfie + GPS), admin lihat semua.
 */
class VisitController extends Controller
{
    /**
     * GET /api/v1/visits — admin: semua kunjungan (filter ?employee_id=&date=).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $visits = Visit::with('employee:id,name,position')
            ->when($validated['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($validated['date'] ?? null, fn ($q, $date) => $q->whereDate('visited_at', $date))
            ->orderByDesc('visited_at')
            ->get();

        return response()->json(['data' => $visits]);
    }

    /**
     * GET /api/v1/visits/me — karyawan: kunjungan sendiri (filter ?date=).
     */
    public function myVisits(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $visits = $this->employee($request)->visits()
            ->when($validated['date'] ?? null, fn ($q, $date) => $q->whereDate('visited_at', $date))
            ->orderByDesc('visited_at')
            ->get();

        return response()->json(['data' => $visits]);
    }

    /**
     * POST /api/v1/visits — karyawan: catat kunjungan baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'visited_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
        ]);

        $visit = $this->employee($request)->visits()->create([
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'photo' => $validated['photo'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'visited_at' => $validated['visited_at'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Kunjungan tercatat.',
            'data' => $visit,
        ], 201);
    }

    /**
     * GET /api/v1/visits/{visit} — detail satu kunjungan (foto + koordinat).
     */
    public function show(Visit $visit): JsonResponse
    {
        $visit->load('employee:id,name,position');

        return response()->json(['data' => $visit]);
    }

    private function employee(Request $request): Employee
    {
        return $request->user()->employee;
    }
}
