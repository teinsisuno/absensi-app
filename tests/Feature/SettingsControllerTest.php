<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
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

    public function test_whatsapp_settings_bisa_disimpan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)
            ->putJson('/api/v1/settings', [
                'settings' => [
                    'whatsapp_enabled' => 'true',
                    'whatsapp_gateway_url' => 'http://127.0.0.1:3001',
                    'whatsapp_api_token' => 'rahasia123',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_enabled', 'true')
            ->assertJsonPath('data.whatsapp_gateway_url', 'http://127.0.0.1:3001');

        $this->assertDatabaseHas('settings', ['key' => 'whatsapp_enabled', 'value' => 'true']);
        $this->assertDatabaseHas('settings', ['key' => 'whatsapp_api_token', 'value' => 'rahasia123']);
    }

    public function test_whatsapp_test_kirim_sukses_ke_gateway(): void
    {
        Http::fake([
            '*/api/send' => Http::response(['success' => true, 'to' => '6281234567890@c.us', 'id' => 'WA-TEST-1']),
        ]);

        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)
            ->putJson('/api/v1/settings', [
                'settings' => [
                    'whatsapp_enabled' => 'true',
                    'whatsapp_gateway_url' => 'http://127.0.0.1:3001',
                    'whatsapp_api_token' => 'rahasia123',
                ],
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/settings/whatsapp/test', ['phone' => '081234567890'])
            ->assertOk()
            ->assertJsonPath('message', 'Pesan test terkirim ke nomor 081234567890.');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send')
            && $request->hasHeader('X-API-Token', 'rahasia123')
            && data_get($request->data(), 'to') === '081234567890');
    }

    public function test_whatsapp_test_gagal_kalau_gateway_nonaktif(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        // whatsapp_enabled default false → kirim test harus ditolak halus
        $this->withToken($token)
            ->postJson('/api/v1/settings/whatsapp/test', ['phone' => '081234567890'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Notifikasi WhatsApp nonaktif.');
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
