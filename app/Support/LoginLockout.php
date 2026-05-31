<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginLockout
{
    public const MAX_ATTEMPTS = 5;

    public static function throttleKey(string $email, string $ipAddress): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ipAddress);
    }

    public static function isLocked(string $email, string $ipAddress): bool
    {
        return RateLimiter::tooManyAttempts(static::throttleKey($email, $ipAddress), static::MAX_ATTEMPTS);
    }

    public static function secondsRemaining(string $email, string $ipAddress): int
    {
        return RateLimiter::availableIn(static::throttleKey($email, $ipAddress));
    }

    /**
     * @return array{locked: bool, seconds_remaining: int, max_attempts: int}
     */
    public static function state(string $email, string $ipAddress): array
    {
        $secondsRemaining = static::secondsRemaining($email, $ipAddress);

        return [
            'locked' => static::isLocked($email, $ipAddress),
            'seconds_remaining' => $secondsRemaining,
            'max_attempts' => static::MAX_ATTEMPTS,
        ];
    }
}
