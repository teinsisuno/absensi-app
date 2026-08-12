<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Detail personal karyawan (1:1 dengan employees).
 */
#[Fillable([
    'uuid', 'employee_id', 'nik', 'gender', 'religion', 'blood_type', 'marital_status',
    'place_of_birth', 'date_of_birth', 'address', 'phone', 'email',
    'emergency_contact_name', 'emergency_contact_phone', 'npwp',
    'external_code', 'created_by', 'updated_by',
])]
class EmployeeDetail extends Model
{
    use HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
