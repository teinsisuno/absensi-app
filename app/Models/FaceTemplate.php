<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Template wajah per karyawan (enroll face-api.js). Satu per karyawan.
 */
#[Fillable(['employee_id', 'template', 'mode'])]
class FaceTemplate extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
