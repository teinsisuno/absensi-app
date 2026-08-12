<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeBank;
use App\Models\EmployeeContract;
use App\Models\EmployeeDetail;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFamily;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class EmployeeSubmoduleTest extends TestCase
{
    use DatabaseMigrations;

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
}
