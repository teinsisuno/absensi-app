<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan lembur karyawan — approval dipegang HR (satu-satunya approver).
 */
#[Fillable(['employee_id', 'date', 'start_time', 'end_time', 'reason', 'status', 'approved_by', 'approved_at', 'approval_notes'])]
class OvertimeRequest extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
