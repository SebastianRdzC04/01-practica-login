<?php

namespace App\Listeners;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;

class LogFailedAuthentication
{
    public function handle(Failed $event): void
    {
        $request = request();

        LoginLog::create([
            'user_id' => data_get($event->user, 'id'),
            'event' => LoginLog::EVENT_LOGIN_FAILED,
            'succeeded' => false,
            'email' => data_get($event->credentials, 'email'),
            'role' => data_get($event->user, 'role'),
            'guard' => $event->guard,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'message' => 'Intento de inicio de sesion fallido.',
        ]);

        Log::warning('Authentication failed', [
            'event' => LoginLog::EVENT_LOGIN_FAILED,
            'email' => data_get($event->credentials, 'email'),
            'user_id' => data_get($event->user, 'id'),
            'role' => data_get($event->user, 'role'),
            'ip_address' => $request?->ip(),
        ]);
    }
}
