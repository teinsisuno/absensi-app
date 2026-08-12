<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDetail;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use DatabaseMigrations;

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

    public function test_karyawan_bisa_lihat_profil_sendiri(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [$employee, $token] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        EmployeeDetail::create([
            'employee_id' => $employee->id,
            'nik' => '1234567890',
            'phone' => '081234567890',
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Siti')
            ->assertJsonPath('data.email', 'siti@example.test')
            ->assertJsonPath('data.detail.nik', '1234567890');
    }

    public function test_karyawan_bisa_update_biodata(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [, $token] = $this->makeEmployee('tokoa', 'Siti');

        $this->withToken($token)
            ->putJson('/api/v1/me', [
                'nik' => '0987654321',
                'gender' => 'P',
                'religion' => 'Islam',
                'marital_status' => 'menikah',
                'address' => 'Jl. Merdeka No. 1',
            ])
            ->assertOk()
            ->assertJsonPath('data.detail.nik', '0987654321');
    }

    public function test_karyawan_bisa_lihat_dokumen_sendiri(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [$employee, $token] = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'document_type' => 'KTP',
            'title' => 'KTP',
            'file_path' => 'documents/ktp.pdf',
        ]);
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/me/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'KTP');
    }

    public function test_profile_hanya_untuk_user_ter_link(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        tenancy()->initialize('tokoa');
        $user = User::create([
            'name' => 'Non Karyawan',
            'email' => 'none@example.test',
            'role' => 'employee',
        ]);
        $token = $user->createToken('mobile')->plainTextToken;
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertStatus(403);
    }
}
