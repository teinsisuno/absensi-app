<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya user yang sudah ter-link ke karyawan aktif yang boleh lewat.
 * Token admin (SSO Central) / user tanpa link → 403.
 */
class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->employee || $user->employee->status !== 'active') {
            abort(403, 'Hanya untuk karyawan yang ter-link ke akun.');
        }

        return $next($request);
    }
}
