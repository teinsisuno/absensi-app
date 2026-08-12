<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pola kerja — durasi kerja/istirahat (satuan JAM), aturan lembur & hari kerja.
 * work_day_hours SUDAH termasuk waktu istirahat (jangan tambah wd_rest_hours saat hitung).
 */
#[Fillable([
    'uuid', 'code', 'name', 'description', 'employee_type',
    'cut_off_date', 'sun_overtime', 'work_day', 'sat_type', 'is_half_day_all',
    'work_day_hours', 'half_day_hours', 'wd_rest_hours', 'hd_rest_hours',
    'external_code', 'is_active', 'created_by', 'updated_by',
])]
class WorkPattern extends Model
{
    use HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'cut_off_date' => 'integer',
            'sun_overtime' => 'boolean',
            'work_day' => 'integer',
            'is_half_day_all' => 'boolean',
            'work_day_hours' => 'integer',
            'half_day_hours' => 'integer',
            'wd_rest_hours' => 'integer',
            'hd_rest_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}
