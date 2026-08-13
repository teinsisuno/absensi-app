<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDetail;
use App\Models\InviteCode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InviteCodeTest extends TestCase
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
            'name' => 'HR Tokoa',
            'email' => 'hr@tokoa.com',
            'role' => 'superadmin',
        ]);
        $token = $user->createToken('sso-test')->plainTextToken;
        tenancy()->end();

        return $this->cachedAdminToken = $token;
    }

    private function createEmployee(string $slug, string $name = 'Siti'): Employee
    {
        tenancy()->initialize($slug);
        $employee = Employee::create([
            'name' => $name,
            'position' => 'Kasir',
            'status' => 'active',
        ]);
        tenancy()->end();

        return $employee;
    }

    private function registerUser(string $slug, string $email = 'siti@tokoa.com'): array
    {
        $this->withTenantHost($slug);
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Siti Karyawan',
            'email' => $email,
            'password' => 'rahasia123',
        ])->assertStatus(201);

        return ['token' => $response->json('token')];
    }

    public function test_admin_bisa_generate_kode_unik(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');

        $response = $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['code', 'employee_id', 'expires_at']]);

        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $code);

        tenancy()->initialize('tokoa');
        $this->assertDatabaseHas('invite_codes', ['employee_id' => $employee->id, 'code' => $code]);
        $invite = InviteCode::where('code', $code)->first();
        $this->assertNull($invite->used_at);
        $this->assertTrue($invite->expires_at->greaterThan(now()));
        tenancy()->end();
    }

    public function test_karyawan_sudah_terlink_tidak_bisa_generate_kode(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        tenancy()->initialize('tokoa');
        $user = User::create(['name' => 'Akun', 'email' => 'akun@tokoa.com', 'role' => 'employee']);
        $employee->update(['user_id' => $user->id]);
        tenancy()->end();

        $this->withTenantHost('tokoa');
        $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'sudah ter-link'));
    }

    public function test_verify_invite_menampilkan_nama_karyawan(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');
        $code = $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])
            ->json('data.code');

        $result = $this->registerUser('tokoa');
        $this->withToken($result['token'])
            ->postJson('/api/v1/auth/verify-invite', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('employee.name', 'Siti')
            ->assertJsonPath('employee.position', 'Kasir');
    }

    public function test_kode_unik_kedaluwarsa_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');
        $code = $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])
            ->json('data.code');

        tenancy()->initialize('tokoa');
        InviteCode::where('code', $code)->update(['expires_at' => now()->subMinute()]);
        tenancy()->end();

        $result = $this->registerUser('tokoa');
        $this->withToken($result['token'])
            ->postJson('/api/v1/auth/verify-invite', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'kedaluwarsa'));
    }

    public function test_link_employee_berhasil(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');
        $code = $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])
            ->json('data.code');

        $result = $this->registerUser('tokoa');
        // Reset auth guard cache — di test, guard Sanctum di-cache antar request
        // (artifact Laravel testing), sehingga user admin dari request invite masih kepake.
        $this->app['auth']->forgetGuards();

        $this->withToken($result['token'])
            ->postJson('/api/v1/auth/link-employee', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('employee.name', 'Siti');

        tenancy()->initialize('tokoa');
        $siti = User::where('email', 'siti@tokoa.com')->first();
        $invite = InviteCode::where('code', $code)->first();
        $this->assertNotNull($siti);
        $this->assertSame($siti->id, $invite->used_by, 'used_by harus user siti (bukan admin).');
        $this->assertNotNull($invite->used_at);
        $this->assertSame($siti->id, Employee::find($employee->id)->user_id, 'employee.user_id harus terisi id user siti.');
        tenancy()->end();
    }

    public function test_kode_unik_dipakai_dua_kali_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');
        $code = $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])
            ->json('data.code');

        $result = $this->registerUser('tokoa');
        $this->withToken($result['token'])->postJson('/api/v1/auth/link-employee', ['code' => $code])->assertOk();

        // User kedua mencoba pakai kode yang sama
        $result2 = $this->registerUser('tokoa', 'budi@tokoa.com');
        $this->withToken($result2['token'])
            ->postJson('/api/v1/auth/link-employee', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'sudah terpakai'));
    }

    public function test_karyawan_sudah_terlink_ke_akun_lain_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');

        // Generate kode resmi, tapi karyawan sudah di-link duluan (simulasi)
        $code = $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])
            ->json('data.code');

        tenancy()->initialize('tokoa');
        $other = User::create(['name' => 'Lain', 'email' => 'lain@tokoa.com', 'role' => 'employee']);
        $employee->update(['user_id' => $other->id]);
        tenancy()->end();

        $result = $this->registerUser('tokoa');
        $this->app['auth']->forgetGuards();
        $this->withToken($result['token'])
            ->postJson('/api/v1/auth/link-employee', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'sudah ter-link ke akun lain'));
    }

    public function test_user_sudah_terlink_tidak_bisa_link_karyawan_lain(): void
    {
        $this->provisionTenant('tokoa');
        $emp1 = $this->createEmployee('tokoa', 'Siti');
        $emp2 = $this->createEmployee('tokoa', 'Budi');
        $this->withTenantHost('tokoa');

        // User A link ke emp1 lewat alur normal
        $code1 = $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $emp1->id])->json('data.code');
        $userA = $this->registerUser('tokoa');
        $this->app['auth']->forgetGuards();
        $this->withToken($userA['token'])->postJson('/api/v1/auth/link-employee', ['code' => $code1])->assertOk();

        // HR buat kode untuk emp2 — reset guard dulu (guard masih nyimpen userA dari link1)
        $this->app['auth']->forgetGuards();
        $code2 = $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $emp2->id])->json('data.code');

        $this->app['auth']->forgetGuards();
        $this->withToken($userA['token'])
            ->postJson('/api/v1/auth/link-employee', ['code' => $code2])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'sudah ter-link ke karyawan lain'));
    }

    public function test_generate_kode_unik_auto_kirim_whatsapp_kalau_aktif_dan_punya_nomor(): void
    {
        Http::fake([
            '*/api/send-code' => Http::response(['success' => true, 'id' => 'WA-1']),
        ]);

        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');

        tenancy()->initialize('tokoa');
        Setting::updateOrCreate(['key' => 'whatsapp_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(['key' => 'whatsapp_gateway_url'], ['value' => 'http://127.0.0.1:3001']);
        Setting::updateOrCreate(['key' => 'whatsapp_api_token'], ['value' => 'rahasia123']);
        EmployeeDetail::create(['employee_id' => $employee->id, 'phone' => '081234567890']);
        tenancy()->end();

        $this->withTenantHost('tokoa');
        $response = $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])
            ->assertStatus(201)
            ->assertJsonPath('data.whatsapp_sent', true);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-code')
            && data_get($request->data(), 'code') === $response->json('data.code')
            && data_get($request->data(), 'phone') === '081234567890');
    }

    public function test_generate_kode_unik_tetap_berhasil_kalau_whatsapp_off(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');

        // whatsapp_enabled default false → kode tetap dibuat tanpa kirim WA
        $response = $this->withToken($this->adminToken('tokoa'))
            ->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])
            ->assertStatus(201)
            ->assertJsonPath('data.whatsapp_sent', false);

        $this->assertNotNull($response->json('data.code'));
    }

    public function test_non_admin_tidak_bisa_generate_kode(): void
    {
        $this->provisionTenant('tokoa');
        $employee = $this->createEmployee('tokoa');
        $this->withTenantHost('tokoa');

        // User biasa (tanpa link) tidak boleh akses admin
        $result = $this->registerUser('tokoa');
        $this->withToken($result['token'])
            ->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])
            ->assertStatus(403);

        // Tanpa auth → 401 (flush header + reset guard — keduanya menempel antar request di test)
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/invite-codes', ['employee_id' => $employee->id])->assertStatus(401);
    }
}
