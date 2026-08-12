<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Karyawan per tenant DB — data kepegawaian, akun login ada di users (user_id).
 * Role mobile (karyawan/supervisor/management) dari mobile_role.
 */
#[Fillable(['user_id', 'name', 'photo', 'position', 'mobile_role', 'work_location_id', 'shift_id', 'supervisor_id', 'status'])]
class Employee extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeGroup::class, 'employee_group_members', 'employee_id', 'group_id');
    }

    public function scheduleSnapshots(): HasMany
    {
        return $this->hasMany(ScheduleSnapshot::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function inviteCodes(): HasMany
    {
        return $this->hasMany(InviteCode::class);
    }

    public function faceTemplate(): HasOne
    {
        return $this->hasOne(FaceTemplate::class);
    }

    public function detail(): HasOne
    {
        return $this->hasOne(EmployeeDetail::class);
    }

    public function banks(): HasMany
    {
        return $this->hasMany(EmployeeBank::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function families(): HasMany
    {
        return $this->hasMany(EmployeeFamily::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }
}
