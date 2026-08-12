<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kalender kerja per tahun — sumber libur & hari kerja efektif.
 */
#[Fillable(['uuid', 'name', 'year', 'description', 'external_code', 'is_active', 'created_by', 'updated_by'])]
class WorkingCalendar extends Model
{
    use HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }
}
