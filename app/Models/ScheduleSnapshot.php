<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Snapshot jadwal per karyawan per tanggal — sumber validasi absen harian.
 * Status: scheduled / confirmed / cancelled. Source: manual / work_pattern / holiday / hris_pull.
 */
#[Fillable([
    'uuid', 'employee_id', 'shift_id', 'work_pattern_id', 'date', 'shift_code',
    'work_pattern_type', 'external_code', 'is_holiday', 'is_sat', 'is_sun',
    'is_half_day', 'is_leave', 'is_permit', 'leave_id', 'status', 'source',
    'notes', 'metadata', 'created_by', 'updated_by', 'synced_at',
])]
class ScheduleSnapshot extends Model
{
    use HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_holiday' => 'boolean',
            'is_sat' => 'boolean',
            'is_sun' => 'boolean',
            'is_half_day' => 'boolean',
            'is_leave' => 'boolean',
            'is_permit' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function workPattern(): BelongsTo
    {
        return $this->belongsTo(WorkPattern::class);
    }
}
