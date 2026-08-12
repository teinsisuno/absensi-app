<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config(['absensi.central_base_url' => 'http://central.test']);
    }

    private function fakeCentralLogin(int $status, array $user = []): void
    {
        Http::fake([
            'central.test/api/v1/auth/login' => Http::response(
                array_filter([
                    'user' => $user ?: ['id' => 42, 'name' => 'Budi Owner', 'email' => 'owner@tokoa.com'],
                    'token' => 'central-token',
                ]),
                $status
            ),
        ]);
    }

    public function test_owner_bisa_login_biasa_dengan_akun_central(): void
    {
        $this->provisionTenant('tokoa');
        $this->fakeCentralLogin(200);

        $this->withTenantHost('tokoa');
        $response = $this->postJson('/api/v1/auth/admin-login', [
            'email' => 'owner@tokoa.com',
            'password' => 'rahasia123',
        ]);

        $response->assertOk()
            ->assertJsonPath('tenant', 'tokoa')
            ->assertJsonPath('user.email', 'owner@tokoa.com')
            ->assertJsonPath('user.role', 'superadmin');

        $this->assertNotEmpty($response->json('token'));

        // User tersimpan di DB tenant
        tenancy()->initialize('tokoa');
        $this->assertDatabaseHas('users', [
            'central_user_id' => 42,
            'email' => 'owner@tokoa.com',
            'role' => 'superadmin',
        ]);
        tenancy()->end();
    }

    public function test_login_gagal_saat_central_menolak_kredensial(): void
    {
        $this->provisionTenant('tokoa');
        $this->fakeCentralLogin(422);

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/admin-login', [
            'email' => 'owner@tokoa.com',
            'password' => 'salah123',
        ])->assertStatus(401);
    }

    public function test_login_ditolak_untuk_email_bukan_owner_tenant(): void
    {
        $this->provisionTenant('tokoa');
        // Akun Central valid, tapi bukan owner tenant ini & belum pernah jadi user lokal
        $this->fakeCentralLogin(200, ['id' => 99, 'name' => 'Orang Lain', 'email' => 'orang@lain.com']);

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/admin-login', [
            'email' => 'orang@lain.com',
            'password' => 'rahasia123',
        ])->assertStatus(401);
    }

    public function test_login_central_tidak_terjangkau_mengembalikan_401(): void
    {
        $this->provisionTenant('tokoa');
        Http::fake(['central.test/*' => Http::response('', 500)]);

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/admin-login', [
            'email' => 'owner@tokoa.com',
            'password' => 'rahasia123',
        ])->assertStatus(401);
    }

    public function test_admin_lokal_bisa_login_biasa(): void
    {
        $this->provisionTenant('tokoa');
        $this->fakeCentralLogin(200, ['id' => 77, 'name' => 'Admin Toko', 'email' => 'admin@tokoa.com']);

        // Admin lokal sudah dibuat (misal via SSO dengan role hr)
        tenancy()->initialize('tokoa');
        User::create([
            'central_user_id' => 77,
            'name' => 'Admin Toko',
            'email' => 'admin@tokoa.com',
            'role' => 'hr',
        ]);
        tenancy()->end();

        $this->withTenantHost('tokoa');
        $response = $this->postJson('/api/v1/auth/admin-login', [
            'email' => 'admin@tokoa.com',
            'password' => 'rahasia123',
        ]);

        $response->assertOk()->assertJsonPath('user.role', 'hr');
    }
}
