<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan per-tenant (key-value). Primary key = key string.
 * Kolom updated_at di-set otomatis oleh MySQL (useCurrent).
 */
#[Fillable(['key', 'value', 'updated_by'])]
class Setting extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';
}
