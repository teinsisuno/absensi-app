<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\Holiday;
use App\Models\ScheduleSnapshot;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkingCalendar;
use App\Models\WorkPattern;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AdminScheduleTest extends TestCase
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

    /** Bikin karyawan langsung di DB tenant (biar bisa jadi anggota group / punya jadwal). */
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

    public function test_admin_bisa_crud_group_dan_sync_anggota(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $siti = $this->makeEmployee('tokoa', 'Siti');
        $budi = $this->makeEmployee('tokoa', 'Budi');

        // Buat group
        $created = $this->withToken($token)->postJson('/api/v1/groups', [
            'name' => 'Group Toko A',
            'description' => 'Kasir shift pagi',
        ])->assertStatus(201)->json('data');
        $this->assertSame('Group Toko A', $created['name']);
        $this->assertNotNull($created['uuid']);

        // Sync anggota via update
        $this->withToken($token)->putJson("/api/v1/groups/{$created['id']}", [
            'member_ids' => [$siti->id, $budi->id],
        ])->assertOk()->assertJsonCount(2, 'data.members');

        // List — jumlah anggota kehitung
        $this->withToken($token)->getJson('/api/v1/groups')
            ->assertOk()
            ->assertJsonPath('data.0.members_count', 2);

        // Hapus
        $this->withToken($token)->deleteJson("/api/v1/groups/{$created['id']}")->assertOk();
        $this->withToken($token)->getJson('/api/v1/groups')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_group_bisa_punya_supervisor(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $supervisor = $this->makeEmployee('tokoa', 'Pak Mandor');

        $this->withToken($token)->postJson('/api/v1/groups', [
            'name' => 'Group Gudang',
            'supervisor_id' => $supervisor->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.supervisor.name', 'Pak Mandor');
    }

    public function test_admin_bisa_crud_kalender_kerja_dan_libur(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        // Buat kalender 2026
        $calendar = $this->withToken($token)->postJson('/api/v1/working-calendars', [
            'name' => 'Kalender 2026',
            'year' => 2026,
        ])->assertStatus(201)->json('data');

        // Tambah libur nasional
        $this->withToken($token)->postJson('/api/v1/holidays', [
            'working_calendar_id' => $calendar['id'],
            'date' => '2026-08-17',
            'name' => 'HUT RI',
            'type' => 'nasional',
        ])->assertStatus(201)
            ->assertJsonPath('data.is_national_holiday', true);

        // Tambah libur company
        $this->withToken($token)->postJson('/api/v1/holidays', [
            'working_calendar_id' => $calendar['id'],
            'date' => '2026-09-01',
            'name' => 'Company Day',
            'type' => 'company',
        ])->assertStatus(201)
            ->assertJsonPath('data.is_company_holiday', true);

        // List libur filter kalender
        $this->withToken($token)->getJson('/api/v1/holidays?working_calendar_id='.$calendar['id'])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // List kalender — holidays_count = 2
        $this->withToken($token)->getJson('/api/v1/working-calendars')
            ->assertOk()
            ->assertJsonPath('data.0.holidays_count', 2);
    }

    public function test_admin_bisa_crud_pola_kerja_dan_shift(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        // Pola kerja
        $pattern = $this->withToken($token)->postJson('/api/v1/work-patterns', [
            'code' => 'staf',
            'name' => 'Staf Toko',
            'work_day' => 6,
            'work_day_hours' => 8,
            'wd_rest_hours' => 1,
        ])->assertStatus(201)->json('data');
        $this->assertSame('STAF', $pattern['code']); // di-uppercase

        // Shift pukul 08:00–17:00
        $this->withToken($token)->postJson('/api/v1/shifts', [
            'work_pattern_id' => $pattern['id'],
            'name' => 'Pagi',
            'code' => 'PGI',
            'work_hour_start' => '08:00',
            'work_hour_end' => '17:00',
            'tolerance_minutes' => 15,
        ])->assertStatus(201)
            ->assertJsonPath('data.work_hour_start', '08:00')
            ->assertJsonPath('data.work_pattern.code', 'STAF');

        // List shift filter pola
        $this->withToken($token)->getJson('/api/v1/shifts?work_pattern_id='.$pattern['id'])
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_bisa_set_dan_list_jadwal_snapshot(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $siti = $this->makeEmployee('tokoa', 'Siti');
        $pattern = $this->withToken($token)->postJson('/api/v1/work-patterns', [
            'code' => 'staf',
            'name' => 'Staf Toko',
        ])->json('data');
        $shift = $this->withToken($token)->postJson('/api/v1/shifts', [
            'work_pattern_id' => $pattern['id'],
            'name' => 'Pagi',
            'code' => 'PGI',
            'work_hour_start' => '08:00',
            'work_hour_end' => '17:00',
        ])->json('data');

        // Set jadwal untuk 3 hari
        $this->withToken($token)->postJson('/api/v1/schedule-snapshots', [
            'employee_ids' => [$siti->id],
            'from' => '2026-08-03',
            'to' => '2026-08-05',
            'shift_id' => $shift['id'],
            'work_pattern_id' => $pattern['id'],
        ])->assertStatus(201);

        // List — 3 entri
        $this->withToken($token)->getJson('/api/v1/schedule-snapshots?employee_id='.$siti->id)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.shift_code', 'PGI');

        // Upsert: set ulang 1 tanggal dengan shift beda → tetap 3 entri
        $shift2 = $this->withToken($token)->postJson('/api/v1/shifts', [
            'name' => 'Sore',
            'code' => 'SOR',
            'work_hour_start' => '13:00',
            'work_hour_end' => '22:00',
        ])->json('data');
        $this->withToken($token)->postJson('/api/v1/schedule-snapshots', [
            'employee_ids' => [$siti->id],
            'date' => '2026-08-04',
            'shift_id' => $shift2['id'],
        ])->assertStatus(201);

        $this->withToken($token)->getJson('/api/v1/schedule-snapshots?employee_id='.$siti->id)
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_hanya_admin_yang_boleh_akses_endpoint_jadwal(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        // User employee tanpa role admin
        tenancy()->initialize('tokoa');
        $user = User::create([
            'name' => 'Siti',
            'email' => 'siti@example.test',
            'role' => 'employee',
        ]);
        $token = $user->createToken('mobile')->plainTextToken;
        tenancy()->end();

        $this->withToken($token)->getJson('/api/v1/groups')->assertStatus(403);
        $this->withToken($token)->getJson('/api/v1/schedule-snapshots')->assertStatus(403);
    }

    public function test_dashboard_stats_kembali_ringkasan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->makeEmployee('tokoa', 'Siti');
        $this->withToken($token)->postJson('/api/v1/groups', [
            'name' => 'Group A',
        ])->assertStatus(201);

        $this->withToken($token)->getJson('/api/v1/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.employees_total', 1)
            ->assertJsonPath('data.groups', 1)
            ->assertJsonPath('data.employees_active', 1);
    }
}
