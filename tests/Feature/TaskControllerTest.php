<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class TaskControllerTest extends TestCase
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

    public function test_admin_bisa_membuat_tugas(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');

        $this->withToken($token)
            ->postJson('/api/v1/tasks', [
                'assignee_id' => $siti->id,
                'title' => 'Susun stok gudang',
                'description' => 'Rapikan rak A sampai C.',
                'due_date' => '2026-08-20',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_karyawan_bisa_lihat_tugas_miliknya(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti, $tokenSiti] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        $admin = User::where('email', 'admin@tokoa.com')->first();
        $task = Task::create([
            'created_by' => $admin->id,
            'assignee_id' => $siti->id,
            'title' => 'Susun stok gudang',
            'status' => 'pending',
        ]);
        tenancy()->end();

        $this->withToken($tokenSiti)
            ->getJson('/api/v1/tasks/me')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $task->id);
    }

    public function test_karyawan_bisa_update_status_tugas_miliknya(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti, $tokenSiti] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        $admin = User::where('email', 'admin@tokoa.com')->first();
        $task = Task::create([
            'created_by' => $admin->id,
            'assignee_id' => $siti->id,
            'title' => 'Susun stok gudang',
            'status' => 'pending',
        ]);
        tenancy()->end();

        $this->withToken($tokenSiti)
            ->putJson('/api/v1/tasks/'.$task->id.'/status', ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');
    }

    public function test_karyawan_tidak_bisa_update_tugas_orang_lain(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');
        [, $tokenBudi] = $this->makeEmployee('tokoa', 'Budi');

        tenancy()->initialize('tokoa');
        $admin = User::where('email', 'admin@tokoa.com')->first();
        $task = Task::create([
            'created_by' => $admin->id,
            'assignee_id' => $siti->id,
            'title' => 'Susun stok gudang',
            'status' => 'pending',
        ]);
        tenancy()->end();

        $this->withToken($tokenBudi)
            ->putJson('/api/v1/tasks/'.$task->id.'/status', ['status' => 'done'])
            ->assertStatus(403);
    }

    public function test_admin_bisa_edit_dan_hapus_tugas(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        $admin = User::where('email', 'admin@tokoa.com')->first();
        $task = Task::create([
            'created_by' => $admin->id,
            'assignee_id' => $siti->id,
            'title' => 'Susun stok gudang',
            'status' => 'pending',
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->putJson('/api/v1/tasks/'.$task->id, [
                'assignee_id' => $siti->id,
                'title' => 'Susun stok gudang & label',
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Susun stok gudang & label');

        $this->withToken($token)
            ->deleteJson('/api/v1/tasks/'.$task->id)
            ->assertOk();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_admin_bisa_filter_daftar_tugas(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        [$siti] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        $admin = User::where('email', 'admin@tokoa.com')->first();
        Task::create(['created_by' => $admin->id, 'assignee_id' => $siti->id, 'title' => 'A', 'status' => 'pending']);
        Task::create(['created_by' => $admin->id, 'assignee_id' => $siti->id, 'title' => 'B', 'status' => 'done']);
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/tasks?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson('/api/v1/tasks?assignee_id='.$siti->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
