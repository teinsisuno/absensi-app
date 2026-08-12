<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AnnouncementControllerTest extends TestCase
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

    private function makeEmployee(string $slug, string $name): array
    {
        tenancy()->initialize($slug);
        $user = User::create([
            'name' => $name,
            'email' => strtolower($name).'@example.test',
            'role' => 'employee',
        ]);
        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => $name,
            'position' => 'Kasir',
            'status' => 'active',
        ]);
        $token = $user->createToken('mobile')->plainTextToken;
        tenancy()->end();

        return [$employee, $token];
    }

    public function test_admin_bisa_membuat_pengumuman_publish(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $response = $this->withToken($token)
            ->postJson('/api/v1/announcements', [
                'title' => 'Libur Nasional 17 Agustus',
                'body' => 'Seluruh karyawan libur pada 17 Agustus.',
                'publish' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Libur Nasional 17 Agustus');

        $this->assertNotNull($response->json('data.published_at'));
    }

    public function test_admin_bisa_membuat_draft(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)
            ->postJson('/api/v1/announcements', [
                'title' => 'Draft',
                'body' => 'Belum publish.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.published_at', null);
    }

    public function test_karyawan_hanya_melihat_yang_sudah_publish(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [, $tokenSiti] = $this->makeEmployee('tokoa', 'Siti');

        $this->withToken($token)->postJson('/api/v1/announcements', [
            'title' => 'Published',
            'body' => 'Isi publish.',
            'publish' => true,
        ]);
        $this->withToken($token)->postJson('/api/v1/announcements', [
            'title' => 'Draft',
            'body' => 'Isi draft.',
        ]);

        // Reset guard cache agar request berikutnya (token karyawan) tidak ke-resolve sebagai admin.
        Auth::forgetGuards();

        $this->withToken($tokenSiti)
            ->getJson('/api/v1/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Published');
    }

    public function test_latest_mengembalikan_5_terbaru(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [, $tokenSiti] = $this->makeEmployee('tokoa', 'Siti');

        for ($i = 1; $i <= 7; $i++) {
            $this->withToken($token)->postJson('/api/v1/announcements', [
                'title' => 'Pengumuman '.$i,
                'body' => 'Isi.',
                'publish' => true,
            ]);
        }

        $this->withToken($tokenSiti)
            ->getJson('/api/v1/announcements/latest')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_karyawan_bisa_melihat_detail_pengumuman_publish(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [, $tokenSiti] = $this->makeEmployee('tokoa', 'Siti');

        $announcement = $this->withToken($token)
            ->postJson('/api/v1/announcements', [
                'title' => 'Libur 17 Agustus',
                'body' => 'Semua libur.',
                'publish' => true,
            ])
            ->json('data');

        Auth::forgetGuards();

        $this->withToken($tokenSiti)
            ->getJson('/api/v1/announcements/'.$announcement['id'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Libur 17 Agustus');
    }

    public function test_karyawan_tidak_bisa_lihat_detail_draft(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [, $tokenSiti] = $this->makeEmployee('tokoa', 'Siti');

        $announcement = $this->withToken($token)
            ->postJson('/api/v1/announcements', [
                'title' => 'Draft',
                'body' => 'Belum publish.',
            ])
            ->json('data');

        Auth::forgetGuards();

        $this->withToken($tokenSiti)
            ->getJson('/api/v1/announcements/'.$announcement['id'])
            ->assertStatus(404);
    }

    public function test_admin_bisa_edit_dan_hapus_pengumuman(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        tenancy()->initialize('tokoa');
        $admin = User::where('email', 'admin@tokoa.com')->first();
        $announcement = Announcement::create([
            'created_by' => $admin->id,
            'title' => 'Lama',
            'body' => 'Isi lama.',
            'published_at' => now(),
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->putJson('/api/v1/announcements/'.$announcement->id, [
                'title' => 'Baru',
                'body' => 'Isi baru.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Baru');

        $this->withToken($token)
            ->deleteJson('/api/v1/announcements/'.$announcement->id)
            ->assertOk();

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }
}
