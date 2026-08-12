<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class VisitControllerTest extends TestCase
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

    public function test_karyawan_bisa_membuat_kunjungan(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [, $token] = $this->makeEmployee('tokoa', 'Siti');

        $this->withToken($token)
            ->postJson('/api/v1/visits', [
                'latitude' => -6.2,
                'longitude' => 106.8,
                'photo' => 'data:image/jpeg;base64,ZmFrZQ==',
                'notes' => 'Kunjungan ke toko Mitra Jaya.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.notes', 'Kunjungan ke toko Mitra Jaya.');
    }

    public function test_karyawan_bisa_lihat_kunjungan_sendiri(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [$employee, $token] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        Visit::create([
            'employee_id' => $employee->id,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'notes' => 'Kunjungan pagi.',
            'visited_at' => '2026-08-12 09:30:00',
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/visits/me')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_bisa_lihat_semua_kunjungan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');
        [$budi] = $this->makeEmployee('tokoa', 'Budi');

        tenancy()->initialize('tokoa');
        Visit::create(['employee_id' => $siti->id, 'visited_at' => '2026-08-12 09:30:00', 'notes' => 'A']);
        Visit::create(['employee_id' => $budi->id, 'visited_at' => '2026-08-12 10:00:00', 'notes' => 'B']);
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/visits')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($token)
            ->getJson('/api/v1/visits?employee_id='.$siti->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_bisa_lihat_detail_kunjungan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        $visit = Visit::create([
            'employee_id' => $siti->id,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'photo' => 'data:image/jpeg;base64,ZmFrZQ==',
            'notes' => 'Kunjungan siang.',
            'visited_at' => '2026-08-12 13:00:00',
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/visits/'.$visit->id)
            ->assertOk()
            ->assertJsonPath('data.employee.name', 'Siti')
            ->assertJsonPath('data.notes', 'Kunjungan siang.');
    }

    public function test_karyawan_tidak_bisa_akses_list_global(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [, $token] = $this->makeEmployee('tokoa', 'Budi');

        $this->withToken($token)
            ->getJson('/api/v1/visits')
            ->assertStatus(403);
    }
}
