<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class WorkLocationControllerTest extends TestCase
{
    use DatabaseMigrations;

    private function adminToken(string $slug): string
    {
        tenancy()->initialize($slug);
        $user = User::create([
            'central_user_id' => 1,
            'name' => 'Admin Tokoa',
            'email' => 'admin@tokoa.com',
            'role' => 'superadmin',
        ]);
        $token = $user->createToken('sso-test')->plainTextToken;
        tenancy()->end();

        return $token;
    }

    public function test_admin_bisa_buat_lokasi_kerja(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)->postJson('/api/v1/work-locations', [
            'name' => 'Toko Pusat',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'radius_meter' => 150,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Toko Pusat')
            ->assertJsonPath('data.radius_meter', 150);
    }

    public function test_radius_default_100_saat_tidak_dikirim(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)->postJson('/api/v1/work-locations', [
            'name' => 'Gudang',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.radius_meter', 100);
    }

    public function test_admin_bisa_list_lokasi(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)->postJson('/api/v1/work-locations', [
            'name' => 'Toko Pusat', 'latitude' => -6.2, 'longitude' => 106.8,
        ])->assertStatus(201);
        $this->withToken($token)->postJson('/api/v1/work-locations', [
            'name' => 'Cabang 2', 'latitude' => -6.3, 'longitude' => 106.7,
        ])->assertStatus(201);

        $this->withToken($token)->getJson('/api/v1/work-locations')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_bisa_edit_radius_lokasi(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $created = $this->withToken($token)->postJson('/api/v1/work-locations', [
            'name' => 'Toko Pusat', 'latitude' => -6.2, 'longitude' => 106.8,
        ])->assertStatus(201)->json('data');

        $this->withToken($token)->putJson("/api/v1/work-locations/{$created['id']}", [
            'name' => 'Toko Pusat',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 500,
        ])
            ->assertOk()
            ->assertJsonPath('data.radius_meter', 500);
    }

    public function test_admin_bisa_hapus_lokasi(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $created = $this->withToken($token)->postJson('/api/v1/work-locations', [
            'name' => 'Toko Pusat', 'latitude' => -6.2, 'longitude' => 106.8,
        ])->assertStatus(201)->json('data');

        $this->withToken($token)->deleteJson("/api/v1/work-locations/{$created['id']}")
            ->assertOk();

        $this->withToken($token)->getJson('/api/v1/work-locations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_validasi_koordinat_dan_radius(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)->postJson('/api/v1/work-locations', [
            'name' => 'Salah',
            'latitude' => -95,
            'longitude' => 106.8,
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/work-locations', [
            'name' => 'Salah',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 5,
        ])->assertStatus(422);
    }
}
