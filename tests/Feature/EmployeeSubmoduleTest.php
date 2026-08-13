<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeBank;
use App\Models\EmployeeContract;
use App\Models\EmployeeDetail;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFamily;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class EmployeeSubmoduleTest extends TestCase
{
    use DatabaseMigrations;

    /** Token admin di-cache per test instance (hindari email duplikat saat helper dipanggil 2x). */
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

    private function createEmployee(string $slug): Employee
    {
        tenancy()->initialize($slug);
        $employee = Employee::create([
            'name' => 'Budi',
            'position' => 'Kasir',
            'status' => 'active',
        ]);
        tenancy()->end();

        return $employee;
    }

    public function test_lima_tabel_submodule_employee_bisa_diisi_dan_relasi_terload(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');

        tenancy()->initialize('tokoa');
        EmployeeDetail::create([
            'employee_id' => $employee->id,
            'nik' => '1234567890123456',
            'gender' => 'L',
            'religion' => 'Islam',
            'place_of_birth' => 'Ungaran',
            'date_of_birth' => '1995-05-17',
            'phone' => '081234567890',
            'npwp' => '00.000.000.0-000.000',
        ]);
        EmployeeBank::create([
            'employee_id' => $employee->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_name' => 'Budi',
            'is_default' => true,
        ]);
        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'document_type' => 'KTP',
            'document_number' => '1234567890123456',
            'title' => 'KTP Budi',
            'file_path' => '/storage/documents/ktp-budi.jpg',
            'verification_status' => 'verified',
        ]);
        EmployeeFamily::create([
            'employee_id' => $employee->id,
            'relation' => 'Istri',
            'name' => 'Siti',
            'gender' => 'P',
            'is_dependent' => true,
            'is_emergency_contact' => true,
            'emergency_phone' => '081298765432',
        ]);
        EmployeeContract::create([
            'employee_id' => $employee->id,
            'contract_number' => 'KTR-2026-001',
            'contract_type' => 'pkwt',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'duration_months' => 12,
            'status' => 'active',
        ]);
        tenancy()->end();

        tenancy()->initialize('tokoa');
        $fresh = Employee::with(['detail', 'banks', 'documents', 'families', 'contracts'])->find($employee->id);

        $this->assertNotNull($fresh->detail, 'detail harus terisi.');
        $this->assertSame('L', $fresh->detail->gender);
        $this->assertSame('1995-05-17', $fresh->detail->date_of_birth->format('Y-m-d'));

        $this->assertCount(1, $fresh->banks);
        $this->assertTrue($fresh->banks->first()->is_default);
        $this->assertSame('BCA', $fresh->banks->first()->bank_name);

        $this->assertCount(1, $fresh->documents);
        $this->assertSame('KTP', $fresh->documents->first()->document_type);
        $this->assertSame('verified', $fresh->documents->first()->verification_status);

        $this->assertCount(1, $fresh->families);
        $this->assertSame('Istri', $fresh->families->first()->relation);
        $this->assertTrue($fresh->families->first()->is_dependent);

        $this->assertCount(1, $fresh->contracts);
        $contract = $fresh->contracts->first();
        $this->assertSame('KTR-2026-001', $contract->contract_number);
        $this->assertSame('active', $contract->status);
        $this->assertTrue($contract->is_latest);

        // uuid otomatis terisi (pola HRIS)
        $this->assertNotNull($fresh->detail->uuid);
        $this->assertNotNull($contract->uuid);
        tenancy()->end();
    }

    public function test_detail_employee_bersifat_satu_satu(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');

        tenancy()->initialize('tokoa');
        EmployeeDetail::create(['employee_id' => $employee->id, 'nik' => '111']);
        tenancy()->end();

        $this->expectException(\Illuminate\Database\QueryException::class);
        tenancy()->initialize('tokoa');
        EmployeeDetail::create(['employee_id' => $employee->id, 'nik' => '222']);
    }

    /*
    |--------------------------------------------------------------------------
    | API level (admin token) — endpoint submodule /employees/{id}/…
    |--------------------------------------------------------------------------
    */

    public function test_admin_bisa_buat_karyawan_sekaligus_detail_personal(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/employees', [
                'name' => 'Budi',
                'position' => 'Kasir',
                'mobile_role' => 'supervisor',
                'detail' => [
                    'nik' => '1234567890123456',
                    'gender' => 'L',
                    'phone' => '081234567890',
                    'date_of_birth' => '1995-05-17',
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.detail.nik', '1234567890123456')
            ->assertJsonPath('data.detail.gender', 'L')
            ->assertJsonPath('data.mobile_role', 'supervisor');
    }

    public function test_admin_bisa_upsert_detail_personal(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');
        $token = $this->adminToken('tokoa');

        $this->withToken($token)
            ->putJson("/api/v1/employees/{$employee->id}/detail", [
                'nik' => '1234567890123456',
                'gender' => 'L',
                'religion' => 'Islam',
                'marital_status' => 'Menikah',
                'place_of_birth' => 'Ungaran',
                'date_of_birth' => '1995-05-17',
                'address' => 'Jl. Merdeka No. 1',
                'phone' => '081234567890',
                'email' => 'budi@example.test',
                'npwp' => '00.000.000.0-000.000',
            ])
            ->assertOk()
            ->assertJsonPath('data.nik', '1234567890123456')
            ->assertJsonPath('data.gender', 'L');

        // Upsert kedua kali tidak bikin duplikat (1:1)
        $this->withToken($token)
            ->putJson("/api/v1/employees/{$employee->id}/detail", ['nik' => '999'])
            ->assertOk()
            ->assertJsonPath('data.nik', '999');

        tenancy()->initialize('tokoa');
        $this->assertSame(1, EmployeeDetail::where('employee_id', $employee->id)->count());
        tenancy()->end();
    }

    public function test_admin_bisa_crud_bank(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');
        $token = $this->adminToken('tokoa');

        $bank = $this->withToken($token)
            ->postJson("/api/v1/employees/{$employee->id}/banks", [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_name' => 'Budi',
                'is_default' => true,
            ])
            ->assertStatus(201)
            ->json('data');

        $this->withToken($token)->getJson("/api/v1/employees/{$employee->id}/banks")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->putJson("/api/v1/employees/{$employee->id}/banks/{$bank['id']}", [
                'bank_name' => 'BCA',
                'account_number' => '0987654321',
                'account_name' => 'Budi',
            ])
            ->assertOk()
            ->assertJsonPath('data.account_number', '0987654321');

        $this->withToken($token)
            ->deleteJson("/api/v1/employees/{$employee->id}/banks/{$bank['id']}")
            ->assertOk();

        $this->withToken($token)->getJson("/api/v1/employees/{$employee->id}/banks")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_bisa_crud_keluarga(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');
        $token = $this->adminToken('tokoa');

        $family = $this->withToken($token)
            ->postJson("/api/v1/employees/{$employee->id}/families", [
                'relation' => 'Istri',
                'name' => 'Siti',
                'gender' => 'P',
                'is_dependent' => true,
                'is_emergency_contact' => true,
                'emergency_phone' => '081298765432',
            ])
            ->assertStatus(201)
            ->json('data');

        $this->withToken($token)->getJson("/api/v1/employees/{$employee->id}/families")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->putJson("/api/v1/employees/{$employee->id}/families/{$family['id']}", [
                'relation' => 'Istri',
                'name' => 'Siti Aminah',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Siti Aminah');

        $this->withToken($token)
            ->deleteJson("/api/v1/employees/{$employee->id}/families/{$family['id']}")
            ->assertOk();
    }

    public function test_admin_bisa_crud_kontrak_dan_demote_is_latest(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');
        $token = $this->adminToken('tokoa');

        $c1 = $this->withToken($token)
            ->postJson("/api/v1/employees/{$employee->id}/contracts", [
                'contract_number' => 'KTR-2025-001',
                'contract_type' => 'pkwt',
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_latest', true)
            ->assertJsonPath('data.duration_months', 12) // auto dari rentang tanggal
            ->json('data');

        // Kontrak baru → yang lama di-demote
        $c2 = $this->withToken($token)
            ->postJson("/api/v1/employees/{$employee->id}/contracts", [
                'contract_number' => 'KTR-2026-001',
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-30',
                'duration_months' => 6,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_latest', true)
            ->json('data');

        tenancy()->initialize('tokoa');
        $this->assertFalse(EmployeeContract::find($c1['id'])->is_latest);
        $this->assertTrue(EmployeeContract::find($c2['id'])->is_latest);
        tenancy()->end();

        $this->withToken($token)
            ->putJson("/api/v1/employees/{$employee->id}/contracts/{$c2['id']}", [
                'contract_number' => 'KTR-2026-002',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ])
            ->assertOk()
            ->assertJsonPath('data.contract_number', 'KTR-2026-002');

        $this->withToken($token)
            ->deleteJson("/api/v1/employees/{$employee->id}/contracts/{$c2['id']}")
            ->assertOk();
    }

    public function test_admin_bisa_crud_dokumen(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');
        $token = $this->adminToken('tokoa');

        $doc = $this->withToken($token)
            ->postJson("/api/v1/employees/{$employee->id}/documents", [
                'document_type' => 'KTP',
                'document_number' => '1234567890123456',
                'title' => 'KTP Budi',
                'file_path' => '/storage/documents/ktp-budi.jpg',
                'verification_status' => 'verified',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.document_type', 'KTP')
            ->assertJsonPath('data.verification_status', 'verified')
            ->json('data');

        $this->withToken($token)
            ->putJson("/api/v1/employees/{$employee->id}/documents/{$doc['id']}", [
                'document_type' => 'KTP',
                'document_number' => '1234567890123456',
                'title' => 'KTP Budi (baru)',
                'verification_status' => 'pending',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'KTP Budi (baru)')
            ->assertJsonPath('data.verification_status', 'pending');

        $this->withToken($token)
            ->deleteJson("/api/v1/employees/{$employee->id}/documents/{$doc['id']}")
            ->assertOk();
    }

    public function test_subresource_tidak_bisa_diakses_dari_employee_lain(): void
    {
        $this->provisionTenant('tokoa');
        $employeeA = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');
        $token = $this->adminToken('tokoa');

        tenancy()->initialize('tokoa');
        $bank = EmployeeBank::create([
            'employee_id' => $employeeA->id,
            'bank_name' => 'BCA',
            'account_number' => '123',
            'account_name' => 'Budi',
        ]);
        tenancy()->end();

        tenancy()->initialize('tokoa');
        $employeeB = Employee::create(['name' => 'Orang Lain', 'status' => 'active']);
        tenancy()->end();

        // Edit/delete via employee lain → 404 (sub-resource bukan miliknya)
        $this->withToken($token)
            ->putJson("/api/v1/employees/{$employeeB->id}/banks/{$bank->id}", [
                'bank_name' => 'BCA',
                'account_number' => '999',
                'account_name' => 'X',
            ])
            ->assertStatus(404);

        $this->withToken($token)
            ->deleteJson("/api/v1/employees/{$employeeB->id}/banks/{$bank->id}")
            ->assertStatus(404);
    }

    public function test_token_karyawan_tidak_bisa_akses_submodule(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');

        tenancy()->initialize('tokoa');
        $user = User::create(['name' => 'Siti', 'email' => 'siti@example.test', 'role' => 'employee']);
        Employee::where('id', $employee->id)->update(['user_id' => $user->id]);
        $employeeToken = $user->createToken('mobile')->plainTextToken;
        tenancy()->end();

        $this->withToken($employeeToken)
            ->putJson("/api/v1/employees/{$employee->id}/detail", ['nik' => '123'])
            ->assertStatus(403);

        $this->withToken($employeeToken)
            ->postJson("/api/v1/employees/{$employee->id}/banks", [
                'bank_name' => 'BCA',
                'account_number' => '1',
                'account_name' => 'Siti',
            ])
            ->assertStatus(403);
    }
}
