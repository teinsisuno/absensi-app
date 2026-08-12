<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kontrak kerja karyawan — riwayat kontrak, versi terbaru ditandai is_latest.
 */
#[Fillable([
    'uuid', 'employee_id', 'contract_number', 'contract_type',
    'start_date', 'end_date', 'duration_months', 'document_path', 'notes',
    'version', 'is_latest', 'status', 'expiry_notified_at',
    'external_code', 'created_by', 'updated_by',
])]
class EmployeeContract extends Model
{
    use HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_latest' => 'boolean',
            'expiry_notified_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
