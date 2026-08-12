<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User per tenant DB — semua yang bisa login.
 * - superadmin/hr: dari SSO Central (tanpa password_hash)
 * - employee: registrasi mandiri (email+password), ter-link ke karyawan via kode unik
 */
#[Fillable(['central_user_id', 'name', 'email', 'password_hash', 'pin_hash', 'role'])]
#[Hidden(['password_hash', 'pin_hash', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }
}
