<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeLoginTest extends TestCase
{
    use DatabaseMigrations;

    private function createEmployee(string $slug, string $name, string $pin): Employee
    {
        tenancy()->initialize($slug);
        $employee = Employee::create([
            'name' => $name,
            'position' => 'Kasir',
            'pin_hash' => Hash::make($pin),
            'status' => 'active',
        ]);
        tenancy()->end();

        return $employee;
    }

    public function test_login_pin_benar_mengeluarkan_token(): void
    {
        $this->provisionTenant('tokoa');
        $this->createEmployee('tokoa', 'Siti', '1234');

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/employee-login', [
            'name' => 'Siti',
            'pin' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('employee.name', 'Siti')
            ->assertJsonStructure(['token', 'employee' => ['id', 'name']]);
    }

    public function test_login_pin_salah_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $this->createEmployee('tokoa', 'Siti', '1234');

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/employee-login', [
            'name' => 'Siti',
            'pin' => '9999',
        ])
            ->assertStatus(401);
    }

    public function test_login_nama_tidak_ada_ditolak(): void
    {
        $this->provisionTenant('tokoa');

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/employee-login', [
            'name' => 'Orang Tak Ada',
            'pin' => '1234',
        ])
            ->assertStatus(401);
    }

    public function test_login_pin_salah_5x_terkunci(): void
    {
        $this->provisionTenant('tokoa');
        $this->createEmployee('tokoa', 'Agus', '1234');

        $this->withTenantHost('tokoa');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/employee-login', ['name' => 'Agus', 'pin' => '0000'])
                ->assertStatus(401);
        }

        // Percobaan ke-6 — meski PIN benar, tetap ditolak (lock)
        $this->postJson('/api/v1/auth/employee-login', ['name' => 'Agus', 'pin' => '1234'])
            ->assertStatus(401)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Terlalu banyak percobaan'));
    }

    public function test_pin_divalidasi_4_sampai_6_digit(): void
    {
        $this->provisionTenant('tokoa');

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/employee-login', ['name' => 'Siti', 'pin' => '123'])
            ->assertStatus(422);
    }
}
