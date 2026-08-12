<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use DatabaseMigrations;

    /** Token admin di-cache per test instance (hindari email duplikat saat helper dipanggil 2x). */
    private ?string $cachedAdminToken = null;

    /** @var array<string, string> */
    private array $cachedEmployeeTokens = [];

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

    private function employeeToken(string $slug, string $name = 'Siti'): string
    {
        if (isset($this->cachedEmployeeTokens[$name])) {
            return $this->cachedEmployeeTokens[$name];
        }

        tenancy()->initialize($slug);
        $user = User::create([
            'name' => $name,
            'email' => strtolower($name).'@example.test',
            'role' => 'employee',
        ]);
        Employee::create([
            'user_id' => $user->id,
            'name' => $name,
            'position' => 'Kasir',
            'status' => 'active',
        ]);
        $token = $user->createToken('mobile')->plainTextToken;
        tenancy()->end();

        return $this->cachedEmployeeTokens[$name] = $token;
    }

    public function test_admin_bisa_buat_karyawan(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/employees', [
                'name' => 'Siti',
                'position' => 'Kasir',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['id', 'name']])
            ->assertJsonPath('data.name', 'Siti')
            ->assertJsonPath('data.mobile_role', 'karyawan') // default
            ->assertJsonPath('data.user_id', null); // link via kode unik, bukan di sini
    }

    public function test_admin_bisa_set_mobile_role_supervisor(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/employees', [
                'name' => 'Mandor',
                'mobile_role' => 'supervisor',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.mobile_role', 'supervisor');
    }

    public function test_admin_bisa_list_dan_filter_karyawan(): void
    {
        $this->provisionTenant('tokoa');
        tenancy()->initialize('tokoa');
        Employee::create(['name' => 'Budi', 'position' => 'Kasir', 'status' => 'active']);
        Employee::create(['name' => 'Ani', 'position' => 'Kasir', 'status' => 'inactive']);
        tenancy()->end();

        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($token)->getJson('/api/v1/employees?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Budi');
    }

    public function test_admin_bisa_edit_karyawan(): void
    {
        $this->provisionTenant('tokoa');
        tenancy()->initialize('tokoa');
        $employee = Employee::create(['name' => 'Siti', 'position' => 'Kasir', 'status' => 'active']);
        tenancy()->end();

        $this->withTenantHost('tokoa');
        $this->withToken($this->adminToken('tokoa'))
            ->putJson("/api/v1/employees/{$employee->id}", [
                'name' => 'Siti Updated',
                'position' => 'Supervisor',
                'mobile_role' => 'supervisor',
            ])
            ->assertOk()
            ->assertJsonPath('data.position', 'Supervisor')
            ->assertJsonPath('data.name', 'Siti Updated')
            ->assertJsonPath('data.mobile_role', 'supervisor');
    }

    public function test_admin_bisa_nonaktifkan_karyawan(): void
    {
        $this->provisionTenant('tokoa');
        tenancy()->initialize('tokoa');
        $employee = Employee::create(['name' => 'Siti', 'status' => 'active']);
        tenancy()->end();

        $this->withTenantHost('tokoa');
        $this->withToken($this->adminToken('tokoa'))->deleteJson("/api/v1/employees/{$employee->id}")
            ->assertOk();

        tenancy()->initialize('tokoa');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'inactive']);
        tenancy()->end();
    }

    public function test_token_karyawan_tidak_bisa_akses_endpoint_admin(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($this->employeeToken('tokoa'))
            ->postJson('/api/v1/employees', ['name' => 'X'])
            ->assertStatus(403);

        $this->withToken($this->employeeToken('tokoa'))
            ->getJson('/api/v1/work-locations')
            ->assertStatus(403);
    }

    public function test_validasi_nama_wajib_dan_mobile_role_dibatasi(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');
        $token = $this->adminToken('tokoa');

        $this->withToken($token)->postJson('/api/v1/employees', ['name' => ''])
            ->assertStatus(422);

        // mobile_role hanya boleh nilai yang dikenal
        $this->withToken($token)->postJson('/api/v1/employees', ['name' => 'Boss', 'mobile_role' => 'boss'])
            ->assertStatus(422);

        // Client tidak bisa men-set user_id sendiri (link via kode unik)
        $this->withToken($token)->postJson('/api/v1/employees', ['name' => 'Coba', 'user_id' => 99])
            ->assertStatus(201)
            ->assertJsonPath('data.user_id', null);
    }
}
