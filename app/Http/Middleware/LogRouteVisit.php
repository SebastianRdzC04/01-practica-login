<?php

namespace App\Http\Middleware;

use App\Support\AuthLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRouteVisit
{
    /**
     * Registra en el log de autenticación cada visita exitosa a una ruta protegida.
     *
     * Este middleware se ejecuta después de que la solicitud ha sido procesada por el resto
     * de la cadena (se coloca como último middleware). Si el método HTTP es GET y la respuesta
     * es exitosa (código 2xx), registra un evento de depuración en AuthLog con información
     * detallada: ruta, URL completa, datos del usuario autenticado, dirección IP y agente de
     * usuario. Esto permite auditar el acceso a vistas protegidas y detectar patrones de
     * navegación inusuales.
     *
     * @param  Request  $request  La solicitud HTTP entrante.
     * @param  Closure  $next     Función que delega el procesamiento al siguiente middleware.
     * @return Response            Respuesta HTTP generada por la aplicación, sin modificaciones.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $user = $request->user();

            AuthLog::debug('Protected view visited', [
                'event' => AuthLog::EVENT_ROUTE_VISIT,
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
                'url' => $request->fullUrl(),
                'user_id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Vista protegida visitada: ' . ($request->route()?->getName() ?? $request->path()),
            ]);
        }

        return $response;
    }
}
