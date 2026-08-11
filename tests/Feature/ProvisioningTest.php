<?php

namespace Tests\Feature;

use App\Models\TenantMeta;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProvisioningTest extends TestCase
{
    use DatabaseMigrations;

    public function test_webhook_tanpa_secret_ditolak(): void
    {
        $this->postJson('/api/v1/provisioning/tenant', [
            'tenant_slug' => 'tokoa',
        ])->assertStatus(401);
    }

    public function test_webhook_valid_membuat_tenant_db_dan_meta(): void
    {
        $this->createdTenantSlugs[] = 'tokoa';

        $response = $this->postJson('/api/v1/provisioning/tenant', [
            'tenant_slug' => 'tokoa',
            'tenant_name' => 'Toko A',
            'owner_email' => 'owner@tokoa.com',
            'subscription_id' => 'sub-123',
        ], ['X-Absensi-Webhook-Secret' => 'test-webhook-secret']);

        $response->assertStatus(202);

        // tenant_meta tercatat & active
        $this->assertDatabaseHas('tenant_meta', [
            'slug' => 'tokoa',
            'status' => 'active',
            'db_name' => 'tenant_absensi_tokoa',
        ], 'central');

        // DB tenant benar-benar dibuat + termigrasi
        $dbs = DB::connection('central')->select('SHOW DATABASES');
        $names = array_column($dbs, 'Database');
        $this->assertContains('tenant_absensi_tokoa', $names);

        // Tabel inti tenant ada
        $tables = DB::connection('central')
            ->select('SHOW TABLES FROM `tenant_absensi_tokoa`');
        $tableNames = array_map(fn ($t) => array_values((array) $t)[0], $tables);
        foreach (['users', 'employees', 'work_locations', 'shifts', 'attendances', 'leave_requests', 'personal_access_tokens'] as $table) {
            $this->assertContains($table, $tableNames, "Tabel {$table} harus ada di DB tenant");
        }

        // Domain tenant terdaftar
        $this->assertDatabaseHas('domains', ['domain' => 'tokoa-absensi.megakomsel.com'], 'central');
    }

    public function test_webhook_dipanggil_dua_kali_idempotent(): void
    {
        $this->createdTenantSlugs[] = 'tokob';

        $headers = ['X-Absensi-Webhook-Secret' => 'test-webhook-secret'];
        $payload = [
            'tenant_slug' => 'tokob',
            'tenant_name' => 'Toko B',
            'owner_email' => 'owner@tokob.com',
        ];

        $this->postJson('/api/v1/provisioning/tenant', $payload, $headers)->assertStatus(202);
        $this->postJson('/api/v1/provisioning/tenant', $payload, $headers)->assertStatus(202);

        // Meta cuma 1 baris, DB cuma 1 (tidak dobel)
        $this->assertSame(1, TenantMeta::where('slug', 'tokob')->count());

        $dbs = DB::connection('central')->select("SHOW DATABASES LIKE 'tenant_absensi_tokob'");
        $this->assertCount(1, $dbs);
    }

    public function test_webhook_slug_invalid_ditolak(): void
    {
        $this->postJson('/api/v1/provisioning/tenant', [
            'tenant_slug' => 'Toko A!',
        ], ['X-Absensi-Webhook-Secret' => 'test-webhook-secret'])
            ->assertStatus(422);
    }
}
