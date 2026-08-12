<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Keluarga & tanggungan karyawan.
 */
#[Fillable([
    'uuid', 'employee_id', 'relation', 'name', 'gender', 'nik',
    'date_of_birth', 'education_level', 'occupation',
    'is_dependent', 'is_emergency_contact', 'emergency_phone',
    'external_code', 'created_by', 'updated_by',
])]
class EmployeeFamily extends Model
{
    use HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_dependent' => 'boolean',
            'is_emergency_contact' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
