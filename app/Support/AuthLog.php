<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class AuthLog
{
    public const EVENT_LOGIN_SCREEN_VIEWED = 'login_screen_viewed';

    public const EVENT_LOGIN_ATTEMPT = 'login_attempt';

    public const EVENT_LOGIN_SUCCESS = 'login_success';

    public const EVENT_LOGIN_FAILED = 'login_failed';

    public const EVENT_LOGIN_LOCKOUT = 'login_lockout';

    public const EVENT_LOGOUT = 'logout';

    public const EVENT_REGISTER_ATTEMPT = 'register_attempt';

    public const EVENT_REGISTER_SUCCESS = 'register_success';

    public static function info(string $message, array $context = []): void
    {
        Log::channel('auth')->info($message, static::normalizeContext($context));
    }

    public static function warning(string $message, array $context = []): void
    {
        Log::channel('auth')->warning($message, static::normalizeContext($context));
    }

    private static function normalizeContext(array $context): array
    {
        return array_merge([
            'app_env' => config('app.env'),
        ], $context);
    }
}
