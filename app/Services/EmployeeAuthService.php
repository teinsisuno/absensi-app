<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Auth karyawan via PIN (tanpa email).
 * PIN di-hash; rate limit percobaan gagal (5x → lock 15 menit).
 */
class EmployeeAuthService
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public function attemptLogin(string $name, string $pin): array
    {
        $employee = Employee::where('name', $name)->where('status', 'active')->first();

        // Rate limit per nama karyawan (jangan bocorin apakah nama valid)
        $key = 'pin-login:'.strtolower($name);
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw new \InvalidArgumentException("Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.");
        }

        if (! $employee || ! Hash::check($pin, $employee->pin_hash)) {
            RateLimiter::hit($key, now()->addMinutes(self::LOCK_MINUTES));

            throw new \InvalidArgumentException('Nama atau PIN salah.');
        }

        RateLimiter::clear($key);

        return [
            'employee' => $employee,
            'token' => $employee->createToken('employee-pin')->plainTextToken,
        ];
    }

    /**
     * Generate PIN baru (4-6 digit) & simpan hash-nya. Return PIN plain (hanya ditampilkan ke admin).
     */
    public function resetPin(Employee $employee): string
    {
        $pin = (string) random_int(1000, 999999);
        $employee->update(['pin_hash' => Hash::make($pin)]);

        return $pin;
    }
}
