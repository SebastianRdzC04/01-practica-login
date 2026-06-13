<?php

namespace App\Http\Middleware;

use App\Support\AuthLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Verifica que el usuario autenticado posea al menos uno de los roles especificados.
     *
     * Este middleware protege rutas o grupos de rutas restringiendo el acceso únicamente a
     * usuarios que tengan los roles indicados como parámetros. Si el usuario no está autenticado
     * o su rol no coincide con ninguno de los permitidos, se registra un evento de seguridad en
     * el log de autenticación (AuthLog) con información detallada del intento y se aborta la
     * solicitud con un error 403 (Prohibido). Es útil para implementar control de acceso basado
     * en roles (RBAC) de forma declarativa en las rutas.
     *
     * @param  Request  $request  La solicitud HTTP entrante.
     * @param  Closure  $next     Función que delega el procesamiento al siguiente middleware.
     * @param  string   ...$roles Lista de nombres de roles permitidos para acceder a la ruta.
     * @return Response            Respuesta HTTP del siguiente middleware si el usuario tiene el rol.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *         403 cuando el usuario no posee ninguno de los roles requeridos.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            AuthLog::warning('Unauthorized role access attempt', [
                'event' => AuthLog::EVENT_UNAUTHORIZED_ACCESS,
                'succeeded' => false,
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
                'url' => $request->fullUrl(),
                'user_id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'allowed_roles' => $roles,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Intento de acceso no autorizado a ruta: ' . ($request->route()?->getName() ?? $request->path()),
            ]);

            abort(403);
        }

        return $next($request);
    }
}
