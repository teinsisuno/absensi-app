<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya user admin/owner (login SSO dari Central) yang boleh lewat.
 * Token karyawan (PIN) → 403.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'Akses khusus admin.');
        }

        return $next($request);
    }
}
