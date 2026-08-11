<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkLocationController extends Controller
{
    /**
     * GET /api/v1/work-locations — daftar lokasi kerja (multi-outlet).
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => WorkLocation::orderBy('name')->get()]);
    }

    /**
     * POST /api/v1/work-locations — tambah lokasi kerja + radius GPS.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meter' => ['sometimes', 'integer', 'min:10', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $location = WorkLocation::create([
            ...$validated,
            'radius_meter' => $validated['radius_meter'] ?? 100,
        ]);

        return response()->json(['data' => $location], 201);
    }

    /**
     * PUT /api/v1/work-locations/{id} — edit lokasi (mis. ubah radius).
     */
    public function update(Request $request, WorkLocation $workLocation): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meter' => ['sometimes', 'integer', 'min:10', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $workLocation->update($validated);

        return response()->json(['data' => $workLocation->fresh()]);
    }

    /**
     * DELETE /api/v1/work-locations/{id} — hapus lokasi (relasi karyawan/absen jadi null).
     */
    public function destroy(WorkLocation $workLocation): JsonResponse
    {
        $workLocation->delete();

        return response()->json(['message' => 'Lokasi kerja dihapus.']);
    }
}
