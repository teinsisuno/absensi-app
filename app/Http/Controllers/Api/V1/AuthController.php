<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\EmployeeAuthService;
use App\Services\SsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly SsoService $sso,
        private readonly EmployeeAuthService $employeeAuth,
    ) {
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
     * POST /api/v1/auth/employee-login — login karyawan pakai nama + PIN.
     */
    public function employeeLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pin' => ['required', 'string', 'digits_between:4,6'],
        ]);

        try {
            $result = $this->employeeAuth->attemptLogin($validated['name'], $validated['pin']);

            return response()->json([
                'token' => $result['token'],
                'employee' => $result['employee']->only(['id', 'name', 'position', 'photo']),
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
