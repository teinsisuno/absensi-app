<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use DatabaseMigrations;

    private ?string $cachedAdminToken = null;

    private function adminToken(string $slug): string
    {
        if ($this->cachedAdminToken !== null) {
            return $this->cachedAdminToken;
        }

        tenancy()->initialize($slug);
        $user = User::create([
            'central_user_id' => 1,
            'name' => 'Admin Tokoa',
            'email' => 'admin@tokoa.com',
            'role' => 'superadmin',
        ]);
        $token = $user->createToken('sso-test')->plainTextToken;
        tenancy()->end();

        return $this->cachedAdminToken = $token;
    }

    public function test_admin_bisa_lihat_settings_dengan_default(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.face_mode', 'server')
            ->assertJsonPath('data.invite_expiry_hours', '48')
            ->assertJsonPath('data.default_radius_meter', '100');
    }

    public function test_admin_bisa_update_settings_batch(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)
            ->putJson('/api/v1/settings', [
                'settings' => [
                    'face_mode' => 'client',
                    'default_radius_meter' => '150',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.face_mode', 'client')
            ->assertJsonPath('data.default_radius_meter', '150');

        $this->assertDatabaseHas('settings', ['key' => 'face_mode', 'value' => 'client']);
    }

    public function test_karyawan_tidak_bisa_akses_settings(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        tenancy()->initialize('tokoa');
        $user = User::create([
            'name' => 'Siti',
            'email' => 'siti@example.test',
            'role' => 'employee',
        ]);
        $token = $user->createToken('mobile')->plainTextToken;
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/settings')
            ->assertStatus(403);
    }
}
