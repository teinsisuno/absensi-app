<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rekening bank karyawan.
 */
#[Fillable([
    'uuid', 'employee_id', 'bank_name', 'account_number', 'account_name',
    'branch', 'is_default', 'external_code', 'created_by', 'updated_by',
])]
class EmployeeBank extends Model
{
    use HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
