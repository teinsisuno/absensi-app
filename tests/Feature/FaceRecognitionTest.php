<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class FaceRecognitionTest extends TestCase
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

    /** Embedding 128 dimensi dengan semua nilai 0.1 */
    private function descriptor(float $value = 0.1): string
    {
        return json_encode(array_fill(0, 128, $value));
    }

    public function test_karyawan_bisa_enroll_template_wajah(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [$employee, $token] = $this->makeEmployee('tokoa', 'Siti');

        $this->withToken($token)
            ->postJson('/api/v1/face/enroll', [
                'template' => $this->descriptor(),
                'mode' => 'server',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.employee_id', $employee->id);

        $this->assertDatabaseHas('face_templates', [
            'employee_id' => $employee->id,
            'mode' => 'server',
        ]);
    }

    public function test_enroll_dengan_template_invalid_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [, $token] = $this->makeEmployee('tokoa', 'Budi');

        $this->withToken($token)
            ->postJson('/api/v1/face/enroll', ['template' => 'bukan-json'])
            ->assertStatus(422);
    }

    public function test_verify_wajah_cocok_mengembalikan_match_true(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [, $token] = $this->makeEmployee('tokoa', 'Siti');

        $this->withToken($token)
            ->postJson('/api/v1/face/enroll', ['template' => $this->descriptor()]);

        $this->withToken($token)
            ->postJson('/api/v1/face/verify', ['descriptor' => $this->descriptor()])
            ->assertOk()
            ->assertJsonPath('data.match', true);
    }

    public function test_verify_wajah_beda_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [, $token] = $this->makeEmployee('tokoa', 'Siti');

        $this->withToken($token)
            ->postJson('/api/v1/face/enroll', ['template' => $this->descriptor(0.1)]);

        $this->withToken($token)
            ->postJson('/api/v1/face/verify', ['descriptor' => $this->descriptor(0.9)])
            ->assertStatus(422);
    }

    public function test_verify_tanpa_enroll_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [, $token] = $this->makeEmployee('tokoa', 'Budi');

        $this->withToken($token)
            ->postJson('/api/v1/face/verify', ['descriptor' => $this->descriptor()])
            ->assertStatus(422);
    }

    public function test_status_menunjukan_belum_enroll(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        [, $token] = $this->makeEmployee('tokoa', 'Budi');

        $this->withToken($token)
            ->getJson('/api/v1/face/status')
            ->assertOk()
            ->assertJsonPath('data.enrolled', false);
    }

    public function test_face_endpoint_tidak_bisa_diakses_admin_tanpa_karyawan(): void
    {
        $this->provisionTenant('tokoa');
        $token = $this->adminToken('tokoa');
        $this->withTenantHost('tokoa');

        $this->withToken($token)
            ->getJson('/api/v1/face/status')
            ->assertStatus(403);
    }
}
