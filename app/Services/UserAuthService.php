<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Auth user mobile (registrasi mandiri email+password, PIN login cepat).
 * Password & PIN di-hash; rate limit percobaan PIN salah (5x → lock 15 menit).
 */
class UserAuthService
{
    public const MAX_PIN_ATTEMPTS = 5;

    public const PIN_LOCK_MINUTES = 15;

    public function register(string $name, string $email, string $password): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password_hash' => Hash::make($password),
            'role' => 'employee',
        ]);
    }

    public function issueToken(User $user, string $device = 'mobile'): string
    {
        return $user->createToken($device)->plainTextToken;
    }

    public function setPin(User $user, string $pin): void
    {
        $user->update(['pin_hash' => Hash::make($pin)]);
    }

    /**
     * Login email + password (user registrasi mandiri).
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! $user->password_hash || ! Hash::check($password, $user->password_hash)) {
            throw new InvalidArgumentException('Email atau password salah.');
        }

        return [
            'user' => $user,
            'token' => $this->issueToken($user, 'login'),
        ];
    }

    /**
     * Login cepat pakai PIN (email sebagai identitas).
     */
    public function pinLogin(string $email, string $pin): array
    {
        $key = 'pin-login:'.strtolower($email);

        if (RateLimiter::tooManyAttempts($key, self::MAX_PIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw new InvalidArgumentException("Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.");
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! $user->pin_hash || ! Hash::check($pin, $user->pin_hash)) {
            RateLimiter::hit($key, now()->addMinutes(self::PIN_LOCK_MINUTES));

            throw new InvalidArgumentException('Email atau PIN salah.');
        }

        RateLimiter::clear($key);

        return [
            'user' => $user,
            'token' => $this->issueToken($user, 'pin-login'),
        ];
    }
}
