<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client HTTP untuk WhatsApp Gateway (proyek terpisah: whatsapp-bot).
 * Baca konfigurasi dari settings tenant (whatsapp_enabled / url / token),
 * lalu kirim pesan & kode unik lewat API gateway.
 */
class WhatsAppGatewayService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function isEnabled(): bool
    {
        return $this->settings->get('whatsapp_enabled') === 'true';
    }

    /** Konfigurasi gateway: url + token (tanpa trailing slash). */
    public function config(): array
    {
        return [
            'url' => rtrim((string) $this->settings->get('whatsapp_gateway_url', ''), '/'),
            'token' => (string) $this->settings->get('whatsapp_api_token', ''),
        ];
    }

    /**
     * Kirim kode unik ke nomor karyawan via gateway.
     * Return raw response gateway (id pesan dll).
     *
     * @throws RuntimeException kalau gateway nonaktif / tidak terkonfigurasi / gagal.
     */
    public function sendCode(string $phone, string $code, ?string $name = null): array
    {
        $this->ensureConfigured();

        return $this->post('/api/send-code', [
            'phone' => $phone,
            'code' => $code,
            'name' => $name,
        ]);
    }

    /**
     * Kirim pesan bebas.
     */
    public function sendMessage(string $phone, string $message): array
    {
        $this->ensureConfigured();

        return $this->post('/api/send', [
            'to' => $phone,
            'message' => $message,
        ]);
    }

    /** Cek status gateway (koneksi + nomor bot). Null kalau nonaktif. */
    public function status(): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            $cfg = $this->config();
            if ($cfg['url'] === '') {
                return ['configured' => false, 'error' => 'URL gateway belum diisi.'];
            }

            $resp = Http::timeout(8)->get($cfg['url'].'/api/status');

            if ($resp->failed()) {
                return ['configured' => true, 'error' => 'Gateway tidak merespons (HTTP '.$resp->status().').'];
            }

            return array_merge(['configured' => true], $resp->json() ?? []);
        } catch (ConnectionException) {
            return ['configured' => true, 'error' => 'Gateway tidak terjangkau.'];
        }
    }

    /** Ambil QR code (data URL PNG) buat scan — null kalau nonaktif. */
    public function qr(): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            $cfg = $this->config();
            if ($cfg['url'] === '') {
                return ['configured' => false, 'error' => 'URL gateway belum diisi.'];
            }

            $resp = Http::timeout(8)->get($cfg['url'].'/api/qr');

            if ($resp->failed()) {
                return ['configured' => true, 'error' => 'Gateway tidak merespons (HTTP '.$resp->status().').'];
            }

            return array_merge(['configured' => true], $resp->json() ?? []);
        } catch (ConnectionException) {
            return ['configured' => true, 'error' => 'Gateway tidak terjangkau.'];
        }
    }

    /** Minta gateway restart (proses mati sendiri → Docker restart policy nyalain lagi). */
    public function restart(): array
    {
        $this->ensureConfigured();

        return $this->post('/api/restart', []);
    }

    private function ensureConfigured(): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Notifikasi WhatsApp nonaktif.');
        }

        $cfg = $this->config();

        if ($cfg['url'] === '' || $cfg['token'] === '') {
            throw new RuntimeException('Konfigurasi WhatsApp Gateway belum lengkap (URL & token wajib diisi di Pengaturan).');
        }
    }

    private function post(string $path, array $payload): array
    {
        $cfg = $this->config();

        try {
            $resp = Http::timeout(20)
                ->withHeaders(['X-API-Token' => $cfg['token']])
                ->post($cfg['url'].$path, $payload);
        } catch (ConnectionException) {
            throw new RuntimeException('Gateway WhatsApp tidak terjangkau. Pastikan whatsapp-bot berjalan.');
        }

        if ($resp->failed()) {
            $err = $resp->json('error') ?? ('HTTP '.$resp->status());
            throw new RuntimeException('Gateway WhatsApp error: '.$err);
        }

        return $resp->json() ?? [];
    }
}
