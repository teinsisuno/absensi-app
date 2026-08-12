<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid', 'work_pattern_id', 'name', 'code', 'external_code',
    'work_hour_start', 'work_hour_end', 'shift_checkin_options',
    'check_in_start', 'check_in_end', 'check_out_start', 'check_out_end',
    'is_overnight', 'check_out_overnight_start', 'check_out_overnight_end',
    'tolerance_minutes', 'min_work_hours', 'has_overtime', 'is_active', 'synced_at',
])]
class Shift extends Model
{
    use HasUuid;

    protected function casts(): array
    {
        return [
            'is_overnight' => 'boolean',
            'has_overtime' => 'boolean',
            'tolerance_minutes' => 'integer',
            'min_work_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function workPattern(): BelongsTo
    {
        return $this->belongsTo(WorkPattern::class);
    }
}
