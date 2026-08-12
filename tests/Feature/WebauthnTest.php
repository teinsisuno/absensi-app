<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WebauthnTest extends TestCase
{
    use DatabaseMigrations;

    private function registerUser(string $slug = 'tokoa'): TestResponse
    {
        $this->withTenantHost($slug);

        return $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Biometrik',
            'email' => 'budi@tokoa.com',
            'password' => 'rahasia123',
        ]);
    }

    private function authHeaders(TestResponse $register): array
    {
        return ['Authorization' => 'Bearer '.$register->json('token')];
    }

    public function test_login_options_publik_mengembalikan_challenge(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        $response = $this->postJson('/api/v1/auth/webauthn/login/options');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'challenge',
                'rpId',
                'userVerification',
            ])
            ->assertJsonPath('rpId', 'tokoa-absensi.megakomsel.com');
    }

    public function test_login_biometrik_tidak_dikenal_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $this->registerUser();

        $this->withTenantHost('tokoa');

        // Kredensial asing — belum pernah didaftarkan
        $this->postJson('/api/v1/auth/webauthn/login', [
            'credential' => [
                'id' => 'aGVsbG8td29ybGQta2V5LWlkLXRlc3Q',
                'type' => 'public-key',
                'rawId' => 'aGVsbG8td29ybGQta2V5LWlkLXRlc3Q',
                'response' => [
                    'authenticatorData' => 'U0FTSS1QQVlMT0FEX0FVVEhFTlRJQ0FUT1JfREFUQQ',
                    'clientDataJSON' => 'eyJjaGFsbGVuZ2UiOiJ0ZXN0In0',
                    'signature' => 'c2lnbmF0dXJlLXRlc3Q',
                ],
            ],
        ])->assertStatus(401);
    }

    public function test_register_options_wajib_login(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        $this->postJson('/api/v1/auth/webauthn/register/options')->assertStatus(401);
    }

    public function test_register_options_mengembalikan_creation_options(): void
    {
        $this->provisionTenant('tokoa');
        $register = $this->registerUser();

        $this->withTenantHost('tokoa');

        $this->postJson('/api/v1/auth/webauthn/register/options', [], $this->authHeaders($register))
            ->assertStatus(200)
            ->assertJsonStructure([
                'challenge',
                'rp' => ['id', 'name'],
                'user' => ['id', 'name', 'displayName'],
                'pubKeyCredParams',
                'authenticatorSelection',
            ])
            ->assertJsonPath('rp.id', 'tokoa-absensi.megakomsel.com')
            ->assertJsonPath('authenticatorSelection.authenticatorAttachment', 'platform');
    }

    public function test_register_credential_palsu_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $register = $this->registerUser();

        $this->withTenantHost('tokoa');

        // Kredensial tidak valid — attestation tidak bisa diverifikasi
        $this->postJson('/api/v1/auth/webauthn/register', [
            'name' => 'Fingerprint Test',
            'credential' => [
                'id' => 'aW52YWxpZC1jcmVkZW50aWFsLWlk',
                'type' => 'public-key',
                'rawId' => 'aW52YWxpZC1jcmVkZW50aWFsLWlk',
                'response' => [
                    'clientDataJSON' => 'eyJ0eXBlIjoid2ViYXV0aG4uY3JlYXRlIn0',
                    'attestationObject' => 'aW52YWxpZC1hdHRlc3RhdGlvbg',
                ],
            ],
        ], $this->authHeaders($register))->assertStatus(422);
    }

    public function test_keys_wajib_login_dan_awalnya_kosong(): void
    {
        $this->provisionTenant('tokoa');
        $register = $this->registerUser();

        $this->withTenantHost('tokoa');

        $this->getJson('/api/v1/auth/webauthn/keys', $this->authHeaders($register))
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_keys_tanpa_login_ditolak(): void
    {
        $this->provisionTenant('tokoa');
        $this->withTenantHost('tokoa');

        $this->getJson('/api/v1/auth/webauthn/keys')->assertStatus(401);
    }
}
