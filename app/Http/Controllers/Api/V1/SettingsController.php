<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pengaturan tenant — admin/HR baca & update (batch).
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * GET /api/v1/settings — semua settings (default terisi otomatis).
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->settings->all()]);
    }

    /**
     * PUT /api/v1/settings — batch update.
     * Body: { settings: { key: value, ... } }
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['required', 'string'],
        ]);

        $data = $this->settings->setMany($validated['settings'], $request->user()->id);

        return response()->json([
            'message' => 'Pengaturan disimpan.',
            'data' => $data,
        ]);
    }
}
