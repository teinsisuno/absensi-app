<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dokumen karyawan (KTP, KK, ijazah, sertifikat, dll).
 */
#[Fillable([
    'uuid', 'employee_id', 'document_type', 'document_number', 'title', 'description',
    'file_path', 'file_name', 'file_size', 'mime_type',
    'issue_date', 'expiry_date', 'issued_by', 'verification_status',
    'external_code', 'created_by', 'updated_by',
])]
class EmployeeDocument extends Model
{
    use HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
