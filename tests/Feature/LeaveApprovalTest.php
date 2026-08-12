<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LeaveApprovalTest extends TestCase
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

    private function createLeaveRequest(string $slug, Employee $employee, array $overrides = []): LeaveRequest
    {
        tenancy()->initialize($slug);
        $leave = LeaveRequest::create(array_merge([
            'employee_id' => $employee->id,
            'type' => 'izin',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'reason' => 'Acara keluarga',
            'status' => 'pending',
        ], $overrides));
        tenancy()->end();

        return $leave;
    }

    public function test_admin_bisa_lihat_semua_pengajuan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');
        [$budi] = $this->makeEmployee('tokoa', 'Budi');

        $this->createLeaveRequest('tokoa', $siti, ['type' => 'cuti']);
        $this->createLeaveRequest('tokoa', $budi, ['type' => 'sakit', 'status' => 'approved']);

        $this->withToken($token)
            ->getJson('/api/v1/leave-requests')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($token)
            ->getJson('/api/v1/leave-requests?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_bisa_menyetujui_pengajuan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');
        $leave = $this->createLeaveRequest('tokoa', $siti);

        $this->withToken($token)
            ->postJson('/api/v1/leave-requests/'.$leave->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'approved']);
    }

    public function test_admin_bisa_menolak_dengan_catatan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');
        $leave = $this->createLeaveRequest('tokoa', $siti);

        $this->withToken($token)
            ->postJson('/api/v1/leave-requests/'.$leave->id.'/reject', ['notes' => 'Perlu surat keterangan dokter.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.approval_notes', 'Perlu surat keterangan dokter.');
    }

    public function test_reject_wajib_catatan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');
        $leave = $this->createLeaveRequest('tokoa', $siti);

        $this->withToken($token)
            ->postJson('/api/v1/leave-requests/'.$leave->id.'/reject')
            ->assertStatus(422);
    }

    public function test_tidak_bisa_approve_dua_kali(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');
        $leave = $this->createLeaveRequest('tokoa', $siti);

        $this->withToken($token)->postJson('/api/v1/leave-requests/'.$leave->id.'/approve')->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/leave-requests/'.$leave->id.'/approve')
            ->assertStatus(422);
    }

    public function test_karyawan_tidak_bisa_approve(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [$siti, $token] = $this->makeEmployee('tokoa', 'Siti');
        $leave = $this->createLeaveRequest('tokoa', $siti);

        $this->withToken($token)
            ->postJson('/api/v1/leave-requests/'.$leave->id.'/approve')
            ->assertStatus(403);
    }

    public function test_stats_mengembalikan_ringkasan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');
        [$budi] = $this->makeEmployee('tokoa', 'Budi');

        $this->createLeaveRequest('tokoa', $siti, ['type' => 'izin']);
        $this->createLeaveRequest('tokoa', $budi, ['type' => 'cuti']);
        $this->createLeaveRequest('tokoa', $siti, ['type' => 'sakit', 'status' => 'approved']);

        $this->withToken($token)
            ->getJson('/api/v1/leave-requests/stats')
            ->assertOk()
            ->assertJsonPath('data.pending', 2)
            ->assertJsonPath('data.approved', 1);
    }
}
