<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\User;
use App\Models\WorkLocation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AdminAttendanceRosterTest extends TestCase
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

    private function makeEmployee(string $slug, string $name): Employee
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
        tenancy()->end();

        return $employee;
    }

    private function makeAttendance(string $slug, Employee $employee, string $type, string $datetime, ?string $selfie = null): Attendance
    {
        tenancy()->initialize($slug);
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'recorded_at' => CarbonImmutable::parse($datetime),
            'selfie_photo' => $selfie,
            'status' => 'valid',
        ]);
        tenancy()->end();

        return $attendance;
    }

    public function test_admin_bisa_lihat_roster_absensi_per_tanggal(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $siti = $this->makeEmployee('tokoa', 'Siti');
        $budi = $this->makeEmployee('tokoa', 'Budi');

        $selfie = 'data:image/jpeg;base64,ZmFrZVNlbGZpZQ==';
        $this->makeAttendance('tokoa', $siti, 'clock_in', '2026-08-03 08:05:00', $selfie);
        $this->makeAttendance('tokoa', $siti, 'clock_out', '2026-08-03 17:02:00', $selfie);
        // Budi hanya clock in (belum pulang)
        $this->makeAttendance('tokoa', $budi, 'clock_in', '2026-08-03 09:10:00');

        $response = $this->withToken($token)
            ->getJson('/api/v1/attendance/roster?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->json('data');

        $this->assertCount(31, $response['dates']);
        $this->assertCount(2, $response['employees']);

        $sitiRow = collect($response['employees'])->firstWhere('name', 'Siti');
        $this->assertNotNull($sitiRow);
        $this->assertSame('08:05', $sitiRow['days'][2]['clock_in']); // index 2 = tanggal 03-08
        $this->assertSame('17:02', $sitiRow['days'][2]['clock_out']);
        $this->assertTrue($sitiRow['days'][2]['has_selfie']);
        $this->assertSame(1, $sitiRow['days'][2]['count_in']);
        $this->assertSame(1, $sitiRow['days'][2]['count_out']);

        $budiRow = collect($response['employees'])->firstWhere('name', 'Budi');
        $this->assertSame('09:10', $budiRow['days'][2]['clock_in']);
        $this->assertNull($budiRow['days'][2]['clock_out']);

        // Hari tanpa absen = kosong
        $this->assertNull($sitiRow['days'][0]['clock_in']);
    }

    public function test_roster_bisa_difilter_per_group(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $siti = $this->makeEmployee('tokoa', 'Siti');
        $budi = $this->makeEmployee('tokoa', 'Budi');

        tenancy()->initialize('tokoa');
        $group = EmployeeGroup::create(['name' => 'Kasir Pagi']);
        $group->members()->attach([$siti->id]);
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/attendance/roster?from=2026-08-01&to=2026-08-31&group_id='.$group->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.name', 'Siti');
    }

    public function test_roster_menolak_rentang_terlalu_panjang(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)
            ->getJson('/api/v1/attendance/roster?from=2026-01-01&to=2026-12-31')
            ->assertStatus(422);
    }

    public function test_admin_bisa_lihat_detail_absen_harian_dengan_foto(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $siti = $this->makeEmployee('tokoa', 'Siti');

        tenancy()->initialize('tokoa');
        $location = WorkLocation::create([
            'name' => 'Toko Utama',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'is_active' => true,
        ]);
        tenancy()->end();

        $selfie = 'data:image/jpeg;base64,ZmFrZVNlbGZpZQ==';
        $this->makeAttendance('tokoa', $siti, 'clock_in', '2026-08-03 08:05:00', $selfie);
        $this->makeAttendance('tokoa', $siti, 'clock_out', '2026-08-03 17:02:00', $selfie);

        tenancy()->initialize('tokoa');
        Attendance::where('employee_id', $siti->id)->update(['work_location_id' => $location->id]);
        tenancy()->end();

        $detail = $this->withToken($token)
            ->getJson('/api/v1/attendance/roster/'.$siti->id.'?date=2026-08-03')
            ->assertOk()
            ->json('data');

        $this->assertSame('Siti', $detail['employee']['name']);
        $this->assertSame('2026-08-03', $detail['date']);
        $this->assertCount(2, $detail['records']);

        $clockIn = collect($detail['records'])->firstWhere('type', 'clock_in');
        $this->assertSame('08:05', $clockIn['time']);
        $this->assertSame($selfie, $clockIn['selfie_photo']);
        $this->assertSame('Toko Utama', $clockIn['work_location']);

        $clockOut = collect($detail['records'])->firstWhere('type', 'clock_out');
        $this->assertSame('17:02', $clockOut['time']);
        $this->assertSame($selfie, $clockOut['selfie_photo']);
    }

    public function test_detail_validasi_tanggal_wajib(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $siti = $this->makeEmployee('tokoa', 'Siti');

        $this->withToken($token)
            ->getJson('/api/v1/attendance/roster/'.$siti->id)
            ->assertStatus(422);
    }

    public function test_hanya_admin_yang_boleh_akses_roster(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        tenancy()->initialize('tokoa');
        $user = User::create([
            'name' => 'Siti',
            'email' => 'siti@example.test',
            'role' => 'employee',
        ]);
        $token = $user->createToken('mobile')->plainTextToken;
        tenancy()->end();

        $this->withToken($token)
            ->getJson('/api/v1/attendance/roster?from=2026-08-01&to=2026-08-31')
            ->assertStatus(403);
    }
}
