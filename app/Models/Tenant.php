<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Tenant id = slug string yang kita tentukan sendiri (id_generator = null).
     * Wajib false, kalau tidak Eloquent menimpa id dengan lastInsertId() (0) setelah insert.
     */
    public function getIncrementing(): bool
    {
        return false;
    }
}
