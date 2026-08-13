<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantMeta;
use Exception;
use Illuminate\Support\Facades\Log;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

/**
 * Provisioning tenant Absensi dari webhook Central (1 tenant = 1 DB).
 * Idempotent: webhook terpanggil 2x tidak membuat DB dobel.
 *
 * Catatan: tidak memakai transaksi DB — CREATE DATABASE (DDL) di MySQL
 * melakukan implicit commit, jadi transaksi tidak memberi atomicity.
 * Idempotensi dijaga lewat pengecekan TenantMeta + Tenant (retry aman).
 */
class TenantProvisioningService
{
    public function provision(array $payload): array
    {
        $slug = $payload['tenant_slug'];
        $name = $payload['tenant_name'] ?? $slug;
        $ownerEmail = $payload['owner_email'] ?? null;
        $subscriptionId = $payload['subscription_id'] ?? null;

        // Idempotent: kalau sudah ada & aktif, kembalikan yang existing
        $meta = TenantMeta::where('slug', $slug)->first();
        if ($meta && $meta->status === 'active') {
            return [
                'status' => 'already_provisioned',
                'tenant_meta' => $meta,
                'db_name' => $meta->db_name,
            ];
        }

        // Re-activate kalau statusnya suspended/provisioning (bukan buat dobel)
        if ($meta) {
            $meta->update([
                'status' => 'provisioning',
                'central_tenant_id' => $payload['central_tenant_id'] ?? $meta->central_tenant_id,
            ]);
        } else {
            $meta = TenantMeta::create([
                'central_tenant_id' => $payload['central_tenant_id'] ?? 0,
                'slug' => $slug,
                'db_name' => 'tenant_absensi_'.$slug,
                'status' => 'provisioning',
            ]);
        }

        // Cek tenant stancl — kalau sudah ada, jangan create lagi
        $tenant = Tenant::find($slug);
        if (! $tenant) {
            // Attribute non-id otomatis di-encode VirtualColumn ke kolom JSON `data`
            $tenant = Tenant::create([
                'id' => $slug,
                'name' => $name,
                'central_tenant_id' => $payload['central_tenant_id'] ?? null,
                'owner_email' => $ownerEmail,
                'subscription_id' => $subscriptionId,
            ]);
            // TenantCreated event otomatis menjalankan CreateDatabase + MigrateDatabase.
        } elseif ($meta->status === 'provisioning') {
            // Retry provisioning yang sebelumnya gagal: pastikan DB + migrasi selesai.
            // Catatan: JANGAN andalkan wasChanged('status') — kalau status sebelumnya sudah
            // 'provisioning', update tidak mengubah nilai → wasChanged() false → pipeline
            // di-skip padahal DB belum pernah dibuat (bug: status 'active' tanpa DB).
            // Cek keberadaan DB secara eksplisit lebih aman + idempotent.
            try {
                app()->call([new CreateDatabase($tenant), 'handle']);
                (new MigrateDatabase($tenant))->handle();
            } catch (TenantDatabaseAlreadyExistsException $e) {
                // DB sudah ada dari percobaan sebelumnya — cukup pastikan termigrasi
                (new MigrateDatabase($tenant))->handle();
            }
        }

        // Pastikan domain terdaftar: {slug}-absensi.megakomsel.com
        $domain = config('absensi.tenant_domain_pattern', '{slug}-absensi.megakomsel.com');
        $domain = str_replace('{slug}', $slug, $domain);
        if (! $tenant->domains()->where('domain', $domain)->exists()) {
            $tenant->domains()->create(['domain' => $domain]);
        }

        $meta->update([
            'status' => 'active',
            'provisioned_at' => now(),
        ]);

        return [
            'status' => 'provisioned',
            'tenant_meta' => $meta->fresh(),
            'db_name' => $meta->db_name,
            'domain' => $domain,
        ];
    }
}
