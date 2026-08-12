<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SignedToken;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class SsoTest extends TestCase
{
    use DatabaseMigrations;

    private function signToken(array $payload): string
    {
        return SignedToken::sign($payload, config('absensi.sso_secret'));
    }

    public function test_sso_valid_membuat_user_dan_mengeluarkan_token(): void
    {
        $this->provisionTenant('tokoa');

        $token = $this->signToken([
            'tenant_slug' => 'tokoa',
            'central_user_id' => 42,
            'name' => 'Budi Owner',
            'email' => 'budi@tokoa.com',
            'role' => 'owner',
        ]);

        $this->withTenantHost('tokoa');
        $response = $this->postJson('/api/v1/auth/sso', ['token' => $token]);

        $response->assertOk()
            ->assertJsonPath('tenant', 'tokoa')
            ->assertJsonPath('user.email', 'budi@tokoa.com')
            ->assertJsonPath('user.role', 'superadmin'); // role Central owner → superadmin

        $this->assertNotEmpty($response->json('token'));

        // User tersimpan di DB tenant (bukan central)
        tenancy()->initialize('tokoa');
        $this->assertDatabaseHas('users', [
            'central_user_id' => 42,
            'email' => 'budi@tokoa.com',
            'role' => 'superadmin',
        ]);
        tenancy()->end();
    }

    public function test_sso_token_dipakai_ulang_ditolak(): void
    {
        $this->provisionTenant('tokoa');

        $token = $this->signToken([
            'tenant_slug' => 'tokoa',
            'central_user_id' => 42,
            'name' => 'Budi',
            'email' => 'budi@tokoa.com',
        ]);

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/sso', ['token' => $token])->assertOk();
        $this->postJson('/api/v1/auth/sso', ['token' => $token])->assertStatus(401);
    }

    public function test_sso_token_invalid_ditolak(): void
    {
        $this->provisionTenant('tokoa');

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/sso', ['token' => 'abc.def.ghi'])
            ->assertStatus(401);
    }

    public function test_sso_tenant_belum_diprovisioning_ditolak(): void
    {
        $token = $this->signToken([
            'tenant_slug' => 'belumada',
            'central_user_id' => 1,
            'name' => 'X',
            'email' => 'x@belumada.com',
        ]);

        // Domain belum terdaftar → tenancy middleware menolak akses (404)
        $this->withTenantHost('belumada');
        $this->postJson('/api/v1/auth/sso', ['token' => $token])
            ->assertStatus(404);
    }

    public function test_sso_token_signature_salah_ditolak(): void
    {
        $this->provisionTenant('tokoa');

        // Token ditandatangani dengan secret yang salah
        $token = $this->signToken(['tenant_slug' => 'tokoa', 'central_user_id' => 9, 'email' => 'x@y.com']);
        $parts = explode('.', $token);
        $forged = $parts[0].'.'.strrev($parts[1]);

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/sso', ['token' => $forged])
            ->assertStatus(401);
    }
}
