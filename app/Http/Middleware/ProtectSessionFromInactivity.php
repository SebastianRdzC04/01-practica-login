<?php

namespace App\Http\Middleware;

use App\Support\AuthLog;
use App\Support\InactivityProtection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectSessionFromInactivity
{
    /**
     * Protege la sesión del usuario cerrándola automáticamente tras un período de inactividad.
     *
     * Este middleware verifica en cada solicitud la última actividad registrada del usuario. Si
     * el tiempo transcurrido desde esa actividad supera el límite configurado (server_timeout_seconds),
     * se cierra la sesión, se invalida el token CSRF, se registra un evento de seguridad en AuthLog
     * y se redirige al login con un mensaje informativo. Si la protección por inactividad está
     * deshabilitada para el usuario, se limpian los datos de estado de la sesión. En caso contrario,
     * se actualiza la marca de última actividad y se almacenan los tiempos de advertencia y modal
     * para que el frontend pueda mostrar alertas proactivas al usuario antes del cierre.
     * El modo 'force' permite aplicar la protección incluso si está deshabilitada en la
     * configuración del usuario.
     *
     * @param  Request   $request  La solicitud HTTP entrante.
     * @param  Closure   $next     Función que delega el procesamiento al siguiente middleware.
     * @param  string|null  $mode  Modo de operación: 'force' para forzar la protección
     *                             independientemente de la configuración del usuario.
     * @return Response             Redirección al login si la sesión expiró, o respuesta del
     *                              siguiente middleware si la sesión sigue activa.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
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

            AuthLog::warning('Session expired due to inactivity', [
                'event' => AuthLog::EVENT_INACTIVITY_LOGOUT,
                'succeeded' => false,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'inactivity_seconds' => now()->timestamp - $lastActivityAt,
                'server_timeout_seconds' => $serverTimeoutSeconds,
                'message' => 'Sesion cerrada por inactividad.',
            ]);

            return redirect()->route('login')->with('status', 'Tu sesion se cerro por inactividad.');
        }

        $session->put(InactivityProtection::SESSION_KEY_LAST_ACTIVITY_AT, now()->timestamp);
        $session->put(InactivityProtection::SESSION_KEY_PROTECTED, true);
        $session->put(InactivityProtection::SESSION_KEY_MODAL_TIMEOUT_SECONDS, $config['modal_timeout_seconds']);
        $session->put(InactivityProtection::SESSION_KEY_WARNING_TIMEOUT_SECONDS, $config['warning_timeout_seconds']);
        $session->put(InactivityProtection::SESSION_KEY_SERVER_TIMEOUT_SECONDS, $serverTimeoutSeconds);

        return $next($request);
    }

    /**
     * Elimina todos los datos de protección por inactividad almacenados en la sesión.
     *
     * Este método se invoca cuando la protección por inactividad está deshabilitada para el
     * usuario. Limpia las claves de sesión relacionadas con los tiempos de espera (última
     * actividad, modal, advertencia y servidor) para evitar que queden datos residuales que
     * pudieran interferir con futuras evaluaciones o mostrar estados inconsistentes en el
     * frontend.
     *
     * @param  Request  $request  La solicitud HTTP de la cual se limpiará el estado de sesión.
     * @return void
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
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
