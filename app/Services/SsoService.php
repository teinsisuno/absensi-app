<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantMeta;
use App\Models\User;
use App\Support\SignedToken;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SSO login admin/owner dari Central.
 * Token: signed token (HMAC) short-lived & one-time, berisi tenant_slug, central_user_id, email, role.
 */
class SsoService
{
    public function loginWithToken(string $token): array
    {
        $secret = config('absensi.sso_secret');
        $payload = SignedToken::verify($token, $secret);

        if (! $payload) {
            throw new \InvalidArgumentException('Token SSO tidak valid, sudah terpakai, atau kedaluwarsa.');
        }

        $slug = $payload['tenant_slug'] ?? null;
        if (! $slug) {
            throw new \InvalidArgumentException('Token SSO tidak memuat tenant_slug.');
        }

        // Cek tenant_meta — tenant harus sudah diprovisioning
        // Pakai connection central EKSPLISIT: di titik ini tenancy sudah di-init middleware
        // (default connection = DB tenant, sedangkan tenant_meta ada di central).
        $meta = TenantMeta::on('central')->where('slug', $slug)->first();
        if (! $meta || $meta->status !== 'active') {
            throw new \RuntimeException('Tenant belum aktif / belum diprovisioning.');
        }

        try {
            $tenant = Tenant::find($slug);
            if (! $tenant) {
                throw new \RuntimeException('Tenant tidak ditemukan.');
            }

            // Pastikan tenancy ke tenant ini (di request normal sudah di-init middleware domain)
            if (! tenancy()->initialized || tenancy()->tenant?->id !== $tenant->id) {
                tenancy()->initialize($tenant);
            }

            $user = User::updateOrCreate(
                ['central_user_id' => $payload['central_user_id']],
                [
                    'name' => $payload['name'] ?? $payload['email'],
                    'email' => $payload['email'],
                    'role' => match ($payload['role'] ?? 'member') {
                        'owner' => 'superadmin',
                        default => 'hr',
                    },
                ]
            );

            return [
                'user' => $user,
                'token' => $user->createToken('sso-'.($payload['role'] ?? 'admin'))->plainTextToken,
            ];
        } catch (Throwable $e) {
            Log::error('SSO login gagal', ['slug' => $slug, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
