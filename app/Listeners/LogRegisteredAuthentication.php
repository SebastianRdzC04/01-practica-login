<?php

namespace App\Listeners;

use App\Support\AuthLog;
use Illuminate\Auth\Events\Registered;

class LogRegisteredAuthentication
{
    public function handle(Registered $event): void
    {
        $request = request();

        AuthLog::info('User registered successfully', [
            'event' => AuthLog::EVENT_REGISTER_SUCCESS,
            'succeeded' => true,
            'user_id' => data_get($event->user, 'id'),
            'email' => data_get($event->user, 'email'),
            'role' => data_get($event->user, 'role'),
            'guard' => 'web',
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'message' => 'Registro completado correctamente.',
        ]);
    }
}
