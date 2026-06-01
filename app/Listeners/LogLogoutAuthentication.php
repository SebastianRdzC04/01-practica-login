<?php

namespace App\Listeners;

use App\Support\AuthLog;
use Illuminate\Auth\Events\Logout;

class LogLogoutAuthentication
{
    public function handle(Logout $event): void
    {
        $request = request();

        AuthLog::info('User logged out', [
            'event' => AuthLog::EVENT_LOGOUT,
            'succeeded' => true,
            'user_id' => data_get($event->user, 'id'),
            'email' => data_get($event->user, 'email'),
            'role' => data_get($event->user, 'role'),
            'guard' => $event->guard,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'message' => 'Cierre de sesion exitoso.',
        ]);
    }
}
