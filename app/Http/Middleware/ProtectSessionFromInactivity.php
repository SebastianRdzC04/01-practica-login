<?php

namespace App\Http\Middleware;

use App\Support\InactivityProtection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectSessionFromInactivity
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $config = InactivityProtection::configFor($user);
        $enabled = $mode === 'force' ? true : $config['enabled'];

        if (! $enabled) {
            $this->clearState($request);

            return $next($request);
        }

        $session = $request->session();
        $lastActivityAt = (int) $session->get(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT, now()->timestamp);
        $serverTimeoutSeconds = (int) $config['server_timeout_seconds'];

        if (now()->timestamp - $lastActivityAt >= $serverTimeoutSeconds) {
            $session->forget(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT);
            $session->forget(InactivityProtection::SESSION_KEY_PROTECTED);
            $session->forget(InactivityProtection::SESSION_KEY_MODAL_TIMEOUT_SECONDS);
            $session->forget(InactivityProtection::SESSION_KEY_WARNING_TIMEOUT_SECONDS);
            $session->forget(InactivityProtection::SESSION_KEY_SERVER_TIMEOUT_SECONDS);

            auth()->guard('web')->logout();

            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login')->with('status', 'Tu sesion se cerro por inactividad.');
        }

        $session->put(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT, now()->timestamp);
        $session->put(InactivityProtection::SESSION_KEY_PROTECTED, true);
        $session->put(InactivityProtection::SESSION_KEY_MODAL_TIMEOUT_SECONDS, $config['modal_timeout_seconds']);
        $session->put(InactivityProtection::SESSION_KEY_WARNING_TIMEOUT_SECONDS, $config['warning_timeout_seconds']);
        $session->put(InactivityProtection::SESSION_KEY_SERVER_TIMEOUT_SECONDS, $serverTimeoutSeconds);

        return $next($request);
    }

    private function clearState(Request $request): void
    {
        $request->session()->forget([
            InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT,
            InactivityProtection::SESSION_KEY_PROTECTED,
            InactivityProtection::SESSION_KEY_MODAL_TIMEOUT_SECONDS,
            InactivityProtection::SESSION_KEY_WARNING_TIMEOUT_SECONDS,
            InactivityProtection::SESSION_KEY_SERVER_TIMEOUT_SECONDS,
        ]);
    }
}
