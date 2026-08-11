<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['employee_id', 'work_location_id', 'type', 'recorded_at', 'latitude', 'longitude', 'distance_meter', 'selfie_photo', 'status', 'notes'])]
class Attendance extends Model
{
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }
}
