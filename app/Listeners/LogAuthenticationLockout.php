<?php

namespace App\Listeners;

use App\Support\AuthLog;
use Illuminate\Auth\Events\Lockout;

class LogAuthenticationLockout
{
    public function handle(Lockout $event): void
    {
        $request = $event->request;

        AuthLog::warning('Authentication lockout', [
            'event' => AuthLog::EVENT_LOGIN_LOCKOUT,
            'succeeded' => false,
            'email' => $request->input('email'),
            'guard' => 'web',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Demasiados intentos de inicio de sesion.',
            'metadata' => [
                'throttle_key' => method_exists($request, 'throttleKey') ? $request->throttleKey() : null,
            ],
        ]);
    }
}
