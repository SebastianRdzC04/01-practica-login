<?php

namespace App\Listeners;

use App\Support\AuthLog;
use Illuminate\Auth\Events\Failed;

class LogFailedAuthentication
{
    public function handle(Failed $event): void
    {
        $request = request();

        AuthLog::warning('Authentication failed', [
            'event' => AuthLog::EVENT_LOGIN_FAILED,
            'succeeded' => false,
            'email' => data_get($event->credentials, 'email'),
            'user_id' => data_get($event->user, 'id'),
            'role' => data_get($event->user, 'role'),
            'guard' => $event->guard,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'message' => 'Intento de inicio de sesion fallido.',
        ]);
    }
}
