<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Pengaturan per-tenant (key-value). Default di-seed saat pertama kali dibaca.
 */
class SettingsService
{
    /** Default nilai kalau key belum ada di tabel settings. */
    public const DEFAULTS = [
        'face_mode' => 'server',
        'invite_expiry_hours' => '48',
        'default_radius_meter' => '100',
        'notify_email_hr' => 'false',
        'whatsapp_enabled' => 'false',
        'whatsapp_gateway_url' => '',
        'whatsapp_api_token' => '',
    ];

    /**
     * Ambil semua settings, nilai default diisi otomatis untuk key yang belum ada.
     */
    public function all(): array
    {
        $rows = Setting::pluck('value', 'key')->toArray();

        return array_merge(self::DEFAULTS, $rows);
    }

    /**
     * Ambil satu setting (fallback ke default).
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $value = Setting::where('key', $key)->value('value');

        return $value ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * Batch update setting — return data terbaru (termasuk default yang belum diubah).
     */
    public function setMany(array $settings, ?int $updatedBy = null): array
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_scalar($value) ? (string) $value : json_encode($value), 'updated_by' => $updatedBy]
            );
        }

        return $this->all();
    }
}
