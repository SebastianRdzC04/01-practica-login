<?php

namespace App\Listeners;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogLogoutAuthentication
{
    public function handle(Logout $event): void
    {
        $request = request();

        LoginLog::create([
            'user_id' => data_get($event->user, 'id'),
            'event' => LoginLog::EVENT_LOGOUT,
            'succeeded' => true,
            'email' => data_get($event->user, 'email'),
            'role' => data_get($event->user, 'role'),
            'guard' => $event->guard,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'message' => 'Cierre de sesion exitoso.',
        ]);

        Log::info('User logged out', [
            'event' => LoginLog::EVENT_LOGOUT,
            'user_id' => data_get($event->user, 'id'),
            'email' => data_get($event->user, 'email'),
            'role' => data_get($event->user, 'role'),
            'ip_address' => $request?->ip(),
        ]);
    }
}
