<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use DatabaseMigrations;

    private function adminToken(string $slug): string
    {
        tenancy()->initialize($slug);
        $user = User::create([
            'central_user_id' => 1,
            'name' => 'Admin Tokoa',
            'email' => 'admin@tokoa.com',
            'role' => 'owner',
        ]);
        $token = $user->createToken('sso-test')->plainTextToken;
        tenancy()->end();

        return $token;
    }

    private function employeeToken(string $slug, string $name, string $pin): string
    {
        tenancy()->initialize($slug);
        $employee = Employee::create([
            'name' => $name,
            'position' => 'Kasir',
            'pin_hash' => Hash::make($pin),
            'status' => 'active',
        ]);
        $token = $employee->createToken('employee-pin')->plainTextToken;
        tenancy()->end();

        return $token;
    }

    public function test_admin_bisa_buat_karyawan_dan_mendapat_pin(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        $response = $this->withToken($this->adminToken('tokoa'))->postJson('/api/v1/employees', [
            'name' => 'Siti',
            'position' => 'Kasir',
        ])
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['id', 'name'], 'pin'])
            ->assertJsonPath('data.name', 'Siti');

        $pin = $response->json('pin');
        $this->assertMatchesRegularExpression('/^\d{4,6}$/', $pin);

        // PIN yang dikasih beneran kepakai buat login karyawan
        $this->postJson('/api/v1/auth/employee-login', ['name' => 'Siti', 'pin' => $pin])
            ->assertOk()
            ->assertJsonPath('employee.name', 'Siti');
    }

    public function test_pin_tidak_boleh_sama_dengan_karyawan_aktif_lain(): void
    {
        $this->provisionTenant('tokoa');
        tenancy()->initialize('tokoa');
        Employee::create(['name' => 'A', 'pin_hash' => Hash::make('111111'), 'status' => 'active']);
        Employee::create(['name' => 'B', 'pin_hash' => Hash::make('111111'), 'status' => 'active']);
        tenancy()->end();

        $this->withTenantHost('tokoa');
        $response = $this->withToken($this->adminToken('tokoa'))->postJson('/api/v1/employees', ['name' => 'C'])
            ->assertStatus(201);

        $this->assertNotEquals('111111', $response->json('pin'));
    }

    public function test_admin_bisa_list_dan_filter_karyawan(): void
    {
        $this->provisionTenant('tokoa');
        tenancy()->initialize('tokoa');
        Employee::create(['name' => 'Budi', 'pin_hash' => Hash::make('123456'), 'status' => 'active']);
        Employee::create(['name' => 'Ani', 'pin_hash' => Hash::make('654321'), 'status' => 'inactive']);
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
        $employee = Employee::create(['name' => 'Siti', 'pin_hash' => Hash::make('123456'), 'status' => 'active']);
        tenancy()->end();

        $this->withTenantHost('tokoa');
        $this->withToken($this->adminToken('tokoa'))
            ->putJson("/api/v1/employees/{$employee->id}", [
                'name' => 'Siti Updated',
                'position' => 'Supervisor',
            ])
            ->assertOk()
            ->assertJsonPath('data.position', 'Supervisor')
            ->assertJsonPath('data.name', 'Siti Updated');
    }

    public function test_admin_bisa_reset_pin(): void
    {
        $this->provisionTenant('tokoa');
        tenancy()->initialize('tokoa');
        $employee = Employee::create(['name' => 'Siti', 'pin_hash' => Hash::make('111111'), 'status' => 'active']);
        tenancy()->end();

        $this->withTenantHost('tokoa');
        $response = $this->withToken($this->adminToken('tokoa'))
            ->postJson("/api/v1/employees/{$employee->id}/reset-pin")
            ->assertOk()
            ->assertJsonStructure(['message', 'pin']);

        $newPin = $response->json('pin');
        $this->assertMatchesRegularExpression('/^\d{4,6}$/', $newPin);

        // PIN lama ditolak, PIN baru diterima
        $this->postJson('/api/v1/auth/employee-login', ['name' => 'Siti', 'pin' => '111111'])->assertStatus(401);
        $this->postJson('/api/v1/auth/employee-login', ['name' => 'Siti', 'pin' => $newPin])->assertOk();
    }

    public function test_admin_bisa_nonaktifkan_karyawan(): void
    {
        $this->provisionTenant('tokoa');
        tenancy()->initialize('tokoa');
        $employee = Employee::create(['name' => 'Siti', 'pin_hash' => Hash::make('123456'), 'status' => 'active']);
        tenancy()->end();

        $this->withTenantHost('tokoa');
        $this->withToken($this->adminToken('tokoa'))->deleteJson("/api/v1/employees/{$employee->id}")
            ->assertOk();

        tenancy()->initialize('tokoa');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'inactive']);
        tenancy()->end();

        // Karyawan nonaktif tidak bisa login
        $this->postJson('/api/v1/auth/employee-login', ['name' => 'Siti', 'pin' => '123456'])->assertStatus(401);
    }

    public function test_token_karyawan_tidak_bisa_akses_endpoint_admin(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($this->employeeToken('tokoa', 'Siti', '123456'))
            ->postJson('/api/v1/employees', ['name' => 'X'])
            ->assertStatus(403);

        $this->withToken($this->employeeToken('tokoa', 'Siti', '123456'))
            ->getJson('/api/v1/work-locations')
            ->assertStatus(403);
    }

    public function test_validasi_nama_wajib_dan_pin_tidak_diterima_dari_client(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');
        $token = $this->adminToken('tokoa');

        $this->withToken($token)->postJson('/api/v1/employees', ['name' => ''])
            ->assertStatus(422);

        // Client tidak bisa men-set pin_hash sendiri
        $this->withToken($token)->postJson('/api/v1/employees', ['name' => 'Coba', 'pin_hash' => 'hacked'])
            ->assertStatus(201)
            ->assertJsonMissingPath('data.pin_hash');
    }
}
