<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya user superadmin/HR yang boleh lewat (web management).
 * User employee (mobile, ter-link karyawan) → 403.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! in_array($user->role, ['superadmin', 'hr'], true)) {
            abort(403, 'Akses khusus admin (superadmin/HR).');
        }

        return $next($request);
    }
}
