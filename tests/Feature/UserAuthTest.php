<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAuthTest extends TestCase
{
    use DatabaseMigrations;

    private function registerUser(string $slug = 'tokoa', array $overrides = []): TestResponse
    {
        $this->withTenantHost($slug);

        return $this->postJson('/api/v1/auth/register', array_merge([
            'name' => 'Siti Karyawan',
            'email' => 'siti@tokoa.com',
            'password' => 'rahasia123',
        ], $overrides));
    }

    public function test_register_membuat_user_dan_mengeluarkan_token(): void
    {
        $this->provisionTenant('tokoa');
        $response = $this->registerUser();

        $response
            ->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.role', 'employee');
        $this->assertNotEmpty($response->json('token'));

        tenancy()->initialize('tokoa');
        $stored = User::where('email', 'siti@tokoa.com')->first();
        $this->assertNotNull($stored);
        $this->assertNotEquals('rahasia123', $stored->password_hash);
        $this->assertTrue(Hash::check('rahasia123', $stored->password_hash));
        $this->assertNull($stored->pin_hash); // PIN belum diatur
        tenancy()->end();
    }

    public function test_register_email_duplikat_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $this->registerUser()->assertStatus(201);
        $this->registerUser()->assertStatus(422);
    }

    public function test_register_validasi_password_min_8(): void
    {
        $this->provisionTenant('tokoa');
        $this->registerUser('tokoa', ['password' => '123'])->assertStatus(422);
    }

    public function test_set_pin_berhasil_setelah_register(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->registerUser()->json('token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/set-pin', ['pin' => '1234'])
            ->assertOk()
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'PIN'));

        tenancy()->initialize('tokoa');
        $stored = User::where('email', 'siti@tokoa.com')->first();
        $this->assertTrue(Hash::check('1234', $stored->pin_hash));
        tenancy()->end();
    }

    public function test_set_pin_validasi_4_sampai_6_digit(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->registerUser()->json('token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/set-pin', ['pin' => '123'])
            ->assertStatus(422);
    }

    public function test_set_pin_membutuhkan_auth(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/set-pin', ['pin' => '1234'])->assertStatus(401);
    }

    public function test_login_email_password_berhasil(): void
    {
        $this->provisionTenant('tokoa');
        $this->registerUser();

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/login', [
            'email' => 'siti@tokoa.com',
            'password' => 'rahasia123',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role'], 'employee'])
            ->assertJsonPath('user.email', 'siti@tokoa.com')
            ->assertJsonPath('employee', null); // belum ter-link karyawan
    }

    public function test_login_password_salah_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $this->registerUser();

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/login', [
            'email' => 'siti@tokoa.com',
            'password' => 'salah123',
        ])->assertStatus(401);
    }

    public function test_pin_login_berhasil_setelah_set_pin(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->registerUser()->json('token');
        $this->withToken($token)->postJson('/api/v1/auth/set-pin', ['pin' => '1234'])->assertOk();

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/pin-login', [
            'email' => 'siti@tokoa.com',
            'pin' => '1234',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', 'siti@tokoa.com');
    }

    public function test_pin_login_pin_salah_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->registerUser()->json('token');
        $this->withToken($token)->postJson('/api/v1/auth/set-pin', ['pin' => '1234'])->assertOk();

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/pin-login', ['email' => 'siti@tokoa.com', 'pin' => '9999'])
            ->assertStatus(401);
    }

    public function test_pin_login_sebelum_set_pin_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $this->registerUser();

        $this->withTenantHost('tokoa');
        $this->postJson('/api/v1/auth/pin-login', ['email' => 'siti@tokoa.com', 'pin' => '1234'])
            ->assertStatus(401);
    }

    public function test_pin_login_salah_5x_terkunci(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->registerUser()->json('token');
        $this->withToken($token)->postJson('/api/v1/auth/set-pin', ['pin' => '1234'])->assertOk();

        $this->withTenantHost('tokoa');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/pin-login', ['email' => 'siti@tokoa.com', 'pin' => '0000'])
                ->assertStatus(401);
        }

        // Percobaan ke-6 — meski PIN benar, tetap ditolak (lock)
        $this->postJson('/api/v1/auth/pin-login', ['email' => 'siti@tokoa.com', 'pin' => '1234'])
            ->assertStatus(401)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Terlalu banyak percobaan'));
    }
}
