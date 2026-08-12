<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ExportAttendanceTest extends TestCase
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

    public function test_admin_bisa_export_csv_absen(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        tenancy()->initialize('tokoa');
        $employee = Employee::create([
            'name' => 'Siti',
            'position' => 'Kasir',
            'status' => 'active',
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'type' => 'clock_in',
            'recorded_at' => '2026-08-03 08:05:00',
            'status' => 'valid',
        ]);
        tenancy()->end();

        $response = $this->withToken($token)
            ->get('/api/v1/attendance/export?from=2026-08-01&to=2026-08-31');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type', ''));
        $this->assertStringContainsString('Siti', $response->streamedContent());
        $this->assertStringContainsString('clock_in', $response->streamedContent());
    }

    public function test_export_validasi_rentang_wajib(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)
            ->get('/api/v1/attendance/export')
            ->assertStatus(422);
    }

    public function test_export_menolak_rentang_terlalu_panjang(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)
            ->get('/api/v1/attendance/export?from=2026-01-01&to=2026-12-31')
            ->assertStatus(422);
    }
}
