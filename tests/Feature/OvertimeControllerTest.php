<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class OvertimeControllerTest extends TestCase
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

    public function test_karyawan_bisa_membuat_pengajuan_lembur(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [, $token] = $this->makeEmployee('tokoa', 'Siti');

        $this->withToken($token)
            ->postJson('/api/v1/overtime-requests', [
                'date' => '2026-08-15',
                'start_time' => '18:00',
                'end_time' => '20:00',
                'reason' => 'Stok gudang belum beres.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_pengajuan_lembur_validasi_end_time(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [, $token] = $this->makeEmployee('tokoa', 'Siti');

        $this->withToken($token)
            ->postJson('/api/v1/overtime-requests', [
                'date' => '2026-08-15',
                'start_time' => '20:00',
                'end_time' => '18:00',
                'reason' => 'Salah jam.',
            ])
            ->assertStatus(422);
    }

    public function test_karyawan_bisa_lihat_riwayat_lembur_sendiri(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [$employee, $token] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        OvertimeRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-15',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Stok',
            'status' => 'pending',
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/overtime-requests/me')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_karyawan_bisa_membatalkan_pengajuan_pending(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [$employee, $token] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        $overtime = OvertimeRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-15',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Stok',
            'status' => 'pending',
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->postJson('/api/v1/overtime-requests/'.$overtime->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_karyawan_tidak_bisa_batalkan_pengajuan_orang_lain(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');
        [, $tokenBudi] = $this->makeEmployee('tokoa', 'Budi');

        tenancy()->initialize('tokoa');
        $overtime = OvertimeRequest::create([
            'employee_id' => $siti->id,
            'date' => '2026-08-15',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Stok',
            'status' => 'pending',
        ]);
        tenancy()->end();

        $this->withToken($tokenBudi)
            ->postJson('/api/v1/overtime-requests/'.$overtime->id.'/cancel')
            ->assertStatus(403);
    }

    public function test_admin_bisa_menyetujui_lembur(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        $overtime = OvertimeRequest::create([
            'employee_id' => $siti->id,
            'date' => '2026-08-15',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Stok',
            'status' => 'pending',
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->postJson('/api/v1/overtime-requests/'.$overtime->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_admin_bisa_menolak_lembur_dengan_catatan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        $overtime = OvertimeRequest::create([
            'employee_id' => $siti->id,
            'date' => '2026-08-15',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Stok',
            'status' => 'pending',
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->postJson('/api/v1/overtime-requests/'.$overtime->id.'/reject', ['notes' => 'Over budget.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.approval_notes', 'Over budget.');
    }

    public function test_admin_bisa_lihat_semua_dan_stats(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        OvertimeRequest::create([
            'employee_id' => $siti->id,
            'date' => '2026-08-15',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Stok',
            'status' => 'pending',
        ]);
        OvertimeRequest::create([
            'employee_id' => $siti->id,
            'date' => '2026-08-16',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Laporan',
            'status' => 'approved',
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/overtime-requests')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($token)
            ->getJson('/api/v1/overtime-requests/stats')
            ->assertOk()
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.approved', 1);
    }
}
