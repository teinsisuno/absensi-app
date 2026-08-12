<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AdminAuthService;
use App\Services\InviteCodeService;
use App\Services\SsoService;
use App\Services\UserAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly SsoService $sso,
        private readonly AdminAuthService $adminAuth,
        private readonly UserAuthService $userAuth,
        private readonly InviteCodeService $inviteCode,
    ) {
    }

    /**
     * POST /api/v1/auth/register — registrasi mandiri (email + nama + password).
     * Langsung mengeluarkan token → frontend lanjut ke set-pin.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        try {
            $user = $this->userAuth->register(
                $validated['name'],
                $validated['email'],
                $validated['password']
            );

            return response()->json([
                'message' => 'Registrasi berhasil. Atur PIN untuk login cepat.',
                'token' => $this->userAuth->issueToken($user, 'register'),
                'user' => $user->only(['id', 'name', 'email', 'role']),
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/auth/set-pin — set PIN 4-6 digit (wajib auth — user baru daftar).
     */
    public function setPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'digits_between:4,6'],
        ]);

        $this->userAuth->setPin($request->user(), $validated['pin']);

        return response()->json(['message' => 'PIN berhasil diatur.']);
    }

    /**
     * POST /api/v1/auth/login — login email + password.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->userAuth->login($validated['email'], $validated['password']);

            return response()->json([
                'token' => $result['token'],
                'user' => $result['user']->only(['id', 'name', 'email', 'role']),
                'employee' => $result['user']->employee?->only(['id', 'name', 'position', 'mobile_role']),
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    /**
     * POST /api/v1/auth/pin-login — login cepat pakai PIN (email sebagai identitas).
     */
    public function pinLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'pin' => ['required', 'string', 'digits_between:4,6'],
        ]);

        try {
            $result = $this->userAuth->pinLogin($validated['email'], $validated['pin']);

            return response()->json([
                'token' => $result['token'],
                'user' => $result['user']->only(['id', 'name', 'email', 'role']),
                'employee' => $result['user']->employee?->only(['id', 'name', 'position', 'mobile_role']),
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    /**
     * POST /api/v1/auth/verify-invite — cek kode unik, balikin nama karyawan (preview di UI).
     */
    public function verifyInvite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ]);

        try {
            $employee = $this->inviteCode->verify($validated['code']);

            return response()->json([
                'employee' => $employee->only(['id', 'name', 'position', 'mobile_role']),
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/auth/link-employee — pakai kode unik: link user ↔ karyawan.
     */
    public function linkEmployee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ]);

        try {
            $employee = $this->inviteCode->link($request->user(), $validated['code']);

            return response()->json([
                'message' => 'Akun berhasil ter-link ke data karyawan.',
                'employee' => $employee->only(['id', 'name', 'position', 'mobile_role']),
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/auth/sso — login admin/owner dari Central.
     */
    public function sso(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            $result = $this->sso->loginWithToken($validated['token']);

            return response()->json([
                'token' => $result['token'],
                'user' => $result['user']->only(['id', 'name', 'email', 'role']),
                'tenant' => tenant('id'),
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    /**
     * POST /api/v1/auth/admin-login — login biasa superadmin/HR (akun Central, validasi server-to-server).
     */
    public function adminLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->adminAuth->attemptLogin($validated['email'], $validated['password']);

            return response()->json([
                'token' => $result['token'],
                'user' => $result['user']->only(['id', 'name', 'email', 'role']),
                'tenant' => tenant('id'),
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    /**
     * POST /api/v1/auth/logout — revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
