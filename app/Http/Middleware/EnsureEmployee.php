<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya karyawan (login PIN) yang boleh lewat.
 * Token admin (SSO Central) → 403.
 */
class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Employee) {
            abort(403, 'Hanya untuk karyawan (login PIN).');
        }

        return $next($request);
    }
}
