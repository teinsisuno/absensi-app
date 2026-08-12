<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
{
    use DatabaseMigrations;

    private const LAT = -6.200000;

    private const LNG = 106.816666;

    /**
     * Siapkan tenant + lokasi "Toko Pusat" (radius 100m) + user ter-link karyawan aktif.
     * Return token user (mobile).
     */
    private function setupEmployee(string $slug = 'tokoa'): string
    {
        tenancy()->initialize($slug);
        $location = WorkLocation::create([
            'name' => 'Toko Pusat',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'radius_meter' => 100,
            'is_active' => true,
        ]);
        $user = User::create([
            'name' => 'Siti',
            'email' => 'siti@attendance.test',
            'password_hash' => Hash::make('rahasia123'),
            'role' => 'employee',
        ]);
        Employee::create([
            'user_id' => $user->id,
            'name' => 'Siti',
            'position' => 'Kasir',
            'work_location_id' => $location->id,
            'status' => 'active',
        ]);
        $token = $user->createToken('mobile')->plainTextToken;
        tenancy()->end();

        return $token;
    }

    private function adminToken(string $slug): string
    {
        tenancy()->initialize($slug);
        $user = User::create([
            'central_user_id' => 1,
            'name' => 'Admin',
            'email' => 'admin@tokoa.com',
            'role' => 'superadmin',
        ]);
        $token = $user->createToken('sso-test')->plainTextToken;
        tenancy()->end();

        return $token;
    }

    public function test_clock_in_dalam_radius_berhasil(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->setupEmployee('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)->postJson('/api/v1/attendance/clock-in', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'clock_in')
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.work_location.name', 'Toko Pusat')
            ->assertJsonPath('data.distance_meter', 0);
    }

    public function test_clock_in_di_luar_radius_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->setupEmployee('tokoa');
        $this->withTenantHost('tokoa');

        // ~11 km dari Toko Pusat → pasti di luar radius 100m
        $this->withToken($token)->postJson('/api/v1/attendance/clock-in', [
            'latitude' => -6.300000,
            'longitude' => self::LNG,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'di luar radius'));
    }

    public function test_clock_in_dua_kali_tanpa_clock_out_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->setupEmployee('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)->postJson('/api/v1/attendance/clock-in', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])->assertStatus(201);

        $this->withToken($token)->postJson('/api/v1/attendance/clock-in', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'sudah clock in'));
    }

    public function test_clock_out_tanpa_clock_in_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->setupEmployee('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)->postJson('/api/v1/attendance/clock-out', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Belum ada clock in'));
    }

    public function test_clock_out_setelah_clock_in_berhasil(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->setupEmployee('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)->postJson('/api/v1/attendance/clock-in', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])->assertStatus(201);

        $this->withToken($token)->postJson('/api/v1/attendance/clock-out', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'clock_out')
            ->assertJsonPath('data.status', 'valid');
    }

    public function test_riwayat_sendiri_bisa_difilter_tanggal(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->setupEmployee('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)->postJson('/api/v1/attendance/clock-in', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])->assertStatus(201);

        $today = now()->format('Y-m-d');

        $this->withToken($token)->getJson('/api/v1/attendance/me?date='.$today)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'clock_in');

        $this->withToken($token)->getJson('/api/v1/attendance/me?date=2000-01-01')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_tidak_bisa_clock_in(): void
    {
        $this->provisionTenant('tokoa');
        $this->setupEmployee('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($this->adminToken('tokoa'))->postJson('/api/v1/attendance/clock-in', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])->assertStatus(403);
    }

    public function test_user_belum_terlink_tidak_bisa_clock_in(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        // User terdaftar tapi belum pakai kode unik → bukan karyawan
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Orang Baru',
            'email' => 'baru@tokoa.com',
            'password' => 'rahasia123',
        ])->assertStatus(201);

        $this->withToken($register->json('token'))->postJson('/api/v1/attendance/clock-in', [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])->assertStatus(403);
    }
}
