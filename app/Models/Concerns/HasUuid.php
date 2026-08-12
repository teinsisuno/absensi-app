<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Auto-generate UUID saat create — dipakai model yang butuh kolom uuid
 * (pola HRIS biar integrasi/sync pakai uuid sebagai kunci stabil).
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
