<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuthLog;
use App\Support\InactivityProtection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionActivityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->session()->get(InactivityProtection::SESSION_KEY_PROTECTED, false)) {
            return response()->json(['protected' => false]);
        }

        $request->session()->put(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT, now()->timestamp);

        $user = $request->user();

        AuthLog::debug('Session heartbeat', [
            'event' => AuthLog::EVENT_SESSION_HEARTBEAT,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'role' => $user?->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Latido de sesion recibido.',
        ]);

        return response()->json([
            'protected' => true,
            'last_activity_at' => $request->session()->get(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT),
        ]);
    }
}
