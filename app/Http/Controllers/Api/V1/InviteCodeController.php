<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use App\Services\InviteCodeService;
use App\Services\WhatsAppGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class InviteCodeController extends Controller
{
    public function __construct(
        private readonly InviteCodeService $inviteCode,
        private readonly WhatsAppGatewayService $whatsapp,
    ) {
    }

    /**
     * GET /api/v1/invite-codes — daftar kode unik beserta pemakainya.
     */
    public function index(): JsonResponse
    {
        $codes = InviteCode::with(['employee:id,name', 'creator:id,name', 'usedByUser:id,name'])
            ->latest()
            ->get();

        return response()->json(['data' => $codes]);
    }

    /**
     * POST /api/v1/invite-codes — generate kode unik untuk karyawan (ditampilkan sekali ke HR).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
        ]);

        try {
            $employee = \App\Models\Employee::with('detail')->findOrFail($validated['employee_id']);
            $invite = $this->inviteCode->generate($employee, $request->user());

            // Auto-kirim kode unik via WhatsApp kalau gateway aktif & karyawan punya nomor.
            $whatsappSent = false;
            $whatsappNote = null;
            $phone = $employee->detail?->phone;

            if ($this->whatsapp->isEnabled()) {
                if ($phone) {
                    try {
                        $this->whatsapp->sendCode($phone, $invite->code, $employee->name);
                        $whatsappSent = true;
                    } catch (Throwable $e) {
                        Log::warning('WhatsApp send-code gagal: '.$e->getMessage());
                        $whatsappNote = 'Kode dibuat, tapi gagal terkirim ke WhatsApp: '.$e->getMessage();
                    }
                } else {
                    $whatsappNote = 'Kode dibuat, tapi karyawan belum punya nomor HP (isi di Data Karyawan → Detail).';
                }
            }

            return response()->json([
                'message' => $whatsappSent
                    ? 'Kode unik dibuat dan terkirim ke WhatsApp '.$phone.'.'
                    : 'Kode unik dibuat. Bagikan ke karyawan — hanya tampil sekali.',
                'data' => [
                    'id' => $invite->id,
                    'employee_id' => $invite->employee_id,
                    'code' => $invite->code,
                    'expires_at' => $invite->expires_at,
                    'whatsapp_sent' => $whatsappSent,
                    'whatsapp_note' => $whatsappNote,
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
