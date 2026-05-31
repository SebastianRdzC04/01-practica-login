<?php

namespace App\Listeners;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class LogSuccessfulAuthentication
{
    public function handle(Login $event): void
    {
        $request = request();

        LoginLog::create([
            'user_id' => data_get($event->user, 'id'),
            'event' => LoginLog::EVENT_LOGIN_SUCCESS,
            'succeeded' => true,
            'email' => data_get($event->user, 'email'),
            'role' => data_get($event->user, 'role'),
            'guard' => $event->guard,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'remember' => $event->remember,
            'message' => 'Inicio de sesion exitoso.',
        ]);

        Log::info('Authentication succeeded', [
            'event' => LoginLog::EVENT_LOGIN_SUCCESS,
            'user_id' => data_get($event->user, 'id'),
            'email' => data_get($event->user, 'email'),
            'role' => data_get($event->user, 'role'),
            'ip_address' => $request?->ip(),
            'remember' => $event->remember,
        ]);
    }
}
