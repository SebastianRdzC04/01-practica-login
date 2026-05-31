<?php

namespace App\Listeners;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Log;

class LogAuthenticationLockout
{
    public function handle(Lockout $event): void
    {
        $request = $event->request;

        LoginLog::create([
            'event' => LoginLog::EVENT_LOGIN_LOCKOUT,
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

        Log::warning('Authentication lockout', [
            'event' => LoginLog::EVENT_LOGIN_LOCKOUT,
            'email' => $request->input('email'),
            'ip_address' => $request->ip(),
        ]);
    }
}
