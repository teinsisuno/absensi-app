<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantMeta;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Login biasa owner/admin (email + password akun Central) langsung dari subdomain tenant.
 *
 * Kredensial divalidasi server-to-server ke Central via POST /api/v1/auth/login —
 * satu akun untuk semua app, password tidak disimpan di DB tenant.
 */
class AdminAuthService
{
    public function attemptLogin(string $email, string $password): array
    {
        $slug = tenant('id');

        // Tenant harus sudah diprovisioning & aktif
        $meta = TenantMeta::on('central')->where('slug', $slug)->first();
        if (! $meta || $meta->status !== 'active') {
            throw new \RuntimeException('Tenant belum aktif.');
        }

        // Validasi kredensial ke Central
        $centralBase = rtrim((string) config('absensi.central_base_url'), '/');

        try {
            $response = Http::timeout(10)->post($centralBase.'/api/v1/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);
        } catch (Throwable $e) {
            Log::error('Central login unreachable', ['slug' => $slug, 'error' => $e->getMessage()]);

            throw new \RuntimeException('Layanan Central tidak bisa dihubungi. Coba lagi nanti.');
        }

        // Central blokir user yang email-nya belum diverifikasi (403).
        if ($response->status() === 403) {
            throw new \RuntimeException('Email Central belum diverifikasi. Verifikasi dulu di megakomsel.com, lalu coba lagi.');
        }

        if ($response->status() !== 200 || ! is_array($response->json('user'))) {
            throw new \InvalidArgumentException('Email atau password salah (akun Central).');
        }

        $centralUser = $response->json('user');

        // Pastikan tenancy ke tenant ini
        $tenant = Tenant::find($slug);
        if (! $tenant) {
            throw new \RuntimeException('Tenant tidak ditemukan.');
        }

        if (! tenancy()->initialized || tenancy()->tenant?->id !== $tenant->id) {
            tenancy()->initialize($tenant);
        }

        // Otorisasi: email = owner tenant, ATAU sudah jadi user lokal superadmin/HR (via SSO)
        $isOwner = (string) ($tenant->owner_email ?? '') === $email;
        $localUser = User::where('email', $email)->whereIn('role', ['superadmin', 'hr'])->first();

        if (! $isOwner && ! $localUser) {
            throw new \RuntimeException('Akun ini tidak terdaftar sebagai superadmin/HR tenant ini.');
        }

        $role = $isOwner ? 'superadmin' : ($localUser?->role ?? 'hr');

        $user = User::updateOrCreate(
            ['central_user_id' => $centralUser['id']],
            ['name' => $centralUser['name'] ?? $email, 'email' => $email, 'role' => $role]
        );

        return [
            'user' => $user,
            'token' => $user->createToken('admin-login')->plainTextToken,
        ];
    }
}
