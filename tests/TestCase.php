<?php

namespace Tests;

use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use PDO;

abstract class TestCase extends BaseTestCase
{
    /** Slug tenant yang dibuat selama test (di-drop DB-nya saat tearDown). */
    protected array $createdTenantSlugs = [];

    protected function setUp(): void
    {
        static::ensureTestDatabase();
        parent::setUp();

        // Konsisten dengan withTenantHost() yang hardcode {slug}-absensi.megakomsel.com.
        config(['absensi.tenant_domain_pattern' => '{slug}-absensi.megakomsel.com']);
    }

    /**
     * Pastikan DB test MySQL ada (RefreshDatabase butuh DB yang sudah eksis).
     */
    protected static function ensureTestDatabase(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $pdo = new PDO(
            'mysql:host='.env('DB_HOST', '127.0.0.1').';port='.env('DB_PORT', '3306'),
            env('DB_USERNAME', 'root'),
            env('DB_PASSWORD', '')
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `absensi_central_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $done = true;
    }

    /**
     * Provision tenant lewat service (sama dengan alur webhook, tapi langsung).
     */
    protected function provisionTenant(string $slug, array $extra = []): array
    {
        $this->createdTenantSlugs[] = $slug;

        return app(TenantProvisioningService::class)->provision(array_merge([
            'tenant_slug' => $slug,
            'tenant_name' => ucfirst($slug),
            'owner_email' => "owner@{$slug}.com",
            'central_tenant_id' => 1,
            'subscription_id' => 'sub-test',
        ], $extra));
    }

    /**
     * Bikin request test seolah-olah datang dari subdomain tenant.
     * WAJIB forceRootUrl — Symfony menimpa HTTP_HOST dari URI (baseUrl), bukan dari server vars.
     */
    protected function withTenantHost(string $slug): void
    {
        $host = $slug.'-absensi.megakomsel.com';
        $this->app['url']->forceRootUrl('https://'.$host);
        $this->withServerVariables(['HTTP_HOST' => $host]);
    }

    protected function tearDown(): void
    {
        // Akhiri tenancy dulu — kalau tidak, default connection masih 'tenant' dan
        // migrate:rollback (beforeApplicationDestroyed DatabaseMigrations) gagal
        // karena DB tenant sudah di-drop.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Cleanup DB tenant SEBELUM parent::tearDown() — setelah itu facade db tidak bisa dipakai
        $pdo = DB::connection('central')->getPdo();

        // Drop DB tenant yang dibuat DURING TEST ini saja.
        // ⚠️ JANGAN drop "orphan" lain — server MySQL dipakai bareng dev,
        // DB tenant dev (paijo-1, toko-uji, dst) tidak terdaftar di central TEST
        // dan akan ikut terhapus kalau cleanup terlalu agresif.
        foreach ($this->createdTenantSlugs as $slug) {
            try {
                $pdo->exec("DROP DATABASE IF EXISTS `tenant_absensi_{$slug}`");
            } catch (\Throwable) {
                // ignore — cleanup best effort
            }
        }

        parent::tearDown();
    }
}
