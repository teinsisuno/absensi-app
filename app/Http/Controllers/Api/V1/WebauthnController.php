<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaravelWebauthn\Facades\Webauthn;
use LaravelWebauthn\Models\WebauthnKey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Throwable;
use Webauthn\Util\Base64;

/**
 * WebAuthn (passkey/biometrik) — login sidik jari / Face ID di PWA.
 *
 * Alur:
 * 1. Karyawan login PIN sekali → frontend tawarkan "Aktifkan biometrik"
 *    → POST /auth/webauthn/register/options → navigator.credentials.create()
 *    → POST /auth/webauthn/register (simpan kunci publik).
 * 2. Login berikutnya: POST /auth/webauthn/login/options (userless — tanpa
 *    email) → navigator.credentials.get() → POST /auth/webauthn/login.
 *    Browser menampilkan daftar passkey / prompt sidik jari dari sistem.
 */
class WebauthnController extends Controller
{
    public function __construct(private readonly UserAuthService $userAuth)
    {
    }

    /**
     * POST /api/v1/auth/webauthn/register/options — siapkan opsi pendaftaran
     * kunci biometrik untuk user yang sedang login (wajib auth).
     */
    public function registerOptions(Request $request): JsonResponse
    {
        try {
            $options = Webauthn::prepareAttestation($request->user());

            return response()->json($options);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/auth/webauthn/register — simpan kunci biometrik hasil
     * navigator.credentials.create() (wajib auth).
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'credential' => ['required', 'array'],
        ]);

        try {
            $key = Webauthn::validateAttestation(
                $request->user(),
                $validated['credential'],
                $validated['name'] ?? 'Fingerprint '.now()->format('d/m/Y')
            );

            return response()->json([
                'message' => 'Biometrik berhasil diaktifkan.',
                'key' => $key->only(['id', 'name', 'created_at']),
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Gagal menyimpan biometrik: '.$e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/auth/webauthn/login/options — opsi login passkey.
     * Userless: tanpa email, browser menampilkan passkey yang tersedia
     * untuk RP ID ini (sidik jari / Face ID).
     */
    public function loginOptions(): JsonResponse
    {
        try {
            $options = Webauthn::prepareAssertion(null);

            return response()->json($options);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/auth/webauthn/login — verifikasi assertion dari
     * navigator.credentials.get(), cari user dari credentialId, terbitkan token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'array'],
        ]);

        try {
            $credential = $validated['credential'];

            // Cari user dari credentialId (rawId dari browser = base64url).
            $key = $this->findKeyByCredentialId($credential['id'] ?? $credential['rawId'] ?? null);

            if (! $key) {
                return response()->json(['message' => 'Biometrik tidak dikenal. Silakan login PIN lalu aktifkan ulang.'], 401);
            }

            $user = User::find($key->user_id);

            if (! $user || ! Webauthn::validateAssertion($user, $credential)) {
                return response()->json(['message' => 'Verifikasi biometrik gagal.'], 401);
            }

            return response()->json([
                'token' => $this->userAuth->issueToken($user, 'webauthn'),
                'user' => $user->only(['id', 'name', 'email', 'role']),
                'employee' => $user->employee?->only(['id', 'name', 'position', 'mobile_role']),
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Verifikasi biometrik gagal.'], 401);
        }
    }

    /**
     * GET /api/v1/auth/webauthn/keys — daftar kunci biometrik user (auth).
     */
    public function keys(Request $request): JsonResponse
    {
        $keys = $request->user()->webauthnKeys()->get(['id', 'name', 'created_at']);

        return response()->json(['data' => $keys]);
    }

    /**
     * DELETE /api/v1/auth/webauthn/keys/{id} — hapus kunci biometrik (auth).
     */
    public function destroyKey(Request $request, int $id): JsonResponse
    {
        $deleted = $request->user()->webauthnKeys()->where('id', $id)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Kunci tidak ditemukan.'], 404);
        }

        return response()->json(['message' => 'Biometrik dihapus.']);
    }

    /**
     * Cari WebauthnKey berdasarkan credentialId dari browser.
     * DB menyimpan credentialId sebagai base64url (padded via cast),
     * browser mengirim rawId base64url (biasanya unpadded) — coba dua-duanya.
     */
    private function findKeyByCredentialId(?string $credentialId): ?WebauthnKey
    {
        if (! $credentialId) {
            return null;
        }

        try {
            $binary = Base64::decode($credentialId);

            return WebauthnKey::where('credentialId', Base64UrlSafe::encode($binary))
                ->orWhere('credentialId', Base64UrlSafe::encodeUnpadded($binary))
                ->first();
        } catch (Throwable) {
            return null;
        }
    }
}
