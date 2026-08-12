<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Group karyawan — relasi many-to-many ke employees.
 * supervisor_id = kepala group (dipakai scope supervisor di PWA).
 */
#[Fillable(['uuid', 'name', 'description', 'supervisor_id', 'external_code', 'is_active', 'created_by', 'updated_by'])]
class EmployeeGroup extends Model
{
    use HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_group_members', 'group_id', 'employee_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }
}
