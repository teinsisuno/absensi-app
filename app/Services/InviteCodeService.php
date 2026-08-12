<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\InviteCode;
use App\Models\User;
use InvalidArgumentException;

/**
 * Kode unik dari HR: generate, verifikasi (untuk preview nama), dan link
 * akun user ↔ data karyawan. One-time use + expired.
 */
class InviteCodeService
{
    /** Alfabet tanpa karakter ambigu (I, O, 0, 1). */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const CODE_LENGTH = 8;

    public function generate(Employee $employee, User $createdBy): InviteCode
    {
        if ($employee->user_id !== null) {
            throw new InvalidArgumentException('Karyawan ini sudah ter-link ke akun.');
        }

        do {
            $code = $this->randomCode();
        } while (InviteCode::where('code', $code)->exists());

        $expiryHours = (int) config('absensi.invite_expiry_hours', 48);

        return InviteCode::create([
            'employee_id' => $employee->id,
            'code' => $code,
            'created_by' => $createdBy->id,
            'expires_at' => now()->addHours($expiryHours),
        ]);
    }

    /**
     * Validasi kode unik → kembalikan karyawan (dipakai preview nama di UI).
     */
    public function verify(string $code): Employee
    {
        $invite = InviteCode::where('code', $code)->with('employee')->first();

        if (! $invite) {
            throw new InvalidArgumentException('Kode unik tidak ditemukan.');
        }

        if ($invite->used_at !== null) {
            throw new InvalidArgumentException('Kode unik sudah terpakai.');
        }

        if ($invite->expires_at->isPast()) {
            throw new InvalidArgumentException('Kode unik sudah kedaluwarsa.');
        }

        $employee = $invite->employee;
        if (! $employee || $employee->status !== 'active') {
            throw new InvalidArgumentException('Data karyawan tidak aktif.');
        }

        return $employee;
    }

    /**
     * Pakai kode unik: tandai terpakai + set user_id di karyawan.
     */
    public function link(User $user, string $code): Employee
    {
        $employee = $this->verify($code);

        if ($employee->user_id !== null) {
            throw new InvalidArgumentException('Karyawan ini sudah ter-link ke akun lain.');
        }

        if (Employee::where('user_id', $user->id)->exists()) {
            throw new InvalidArgumentException('Akun ini sudah ter-link ke karyawan lain.');
        }

        InviteCode::where('code', $code)->update([
            'used_at' => now(),
            'used_by' => $user->id,
        ]);

        $employee->update(['user_id' => $user->id]);

        return $employee;
    }

    private function randomCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
