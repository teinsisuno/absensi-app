<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hari libur — nasional / company, milik satu kalender kerja.
 */
#[Fillable(['uuid', 'working_calendar_id', 'date', 'name', 'description', 'type', 'is_national_holiday', 'is_company_holiday', 'external_code', 'created_by', 'updated_by'])]
class Holiday extends Model
{
    use HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_national_holiday' => 'boolean',
            'is_company_holiday' => 'boolean',
        ];
    }

    public function workingCalendar(): BelongsTo
    {
        return $this->belongsTo(WorkingCalendar::class);
    }
}
