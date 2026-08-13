<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Services\WhatsAppGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pengaturan tenant — admin/HR baca & update (batch).
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly WhatsAppGatewayService $whatsapp,
    ) {}

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
            'settings.*' => ['string'], // boleh kosong (mis. URL/token WA belum diisi)
        ]);

        $data = $this->settings->setMany($validated['settings'], $request->user()->id);

        return response()->json([
            'message' => 'Pengaturan disimpan.',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/settings/whatsapp/test — kirim pesan test ke nomor tertentu.
     * Body: { phone: "081234567890" }
     */
    public function testWhatsApp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        try {
            $result = $this->whatsapp->sendMessage($validated['phone'], "✅ Test WhatsApp Gateway berhasil!\n\nGateway absensi-app sudah terhubung. Kode unik karyawan akan dikirim otomatis lewat sini.");

            return response()->json([
                'message' => 'Pesan test terkirim ke nomor '.$validated['phone'].'.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/settings/whatsapp/status — status koneksi gateway.
     */
    public function whatsappStatus(): JsonResponse
    {
        return response()->json(['data' => $this->whatsapp->status()]);
    }
}
