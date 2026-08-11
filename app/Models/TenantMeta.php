<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Mapping tenant di DB Absensi pusat.
 * Bukan model tenant (stancl) — hanya meta/registry untuk provisioning.
 */
#[Fillable(['central_tenant_id', 'slug', 'db_name', 'status', 'provisioned_at'])]
class TenantMeta extends Model
{
    /** Tabel sengaja singular sesuai PRD (tenant_meta). */
    protected $table = 'tenant_meta';

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
