<?php

namespace App\Http\Middleware;

use App\Support\AuthLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Redirige a los usuarios autenticados lejos de rutas públicas (login, registro, etc.).
     *
     * Este middleware se aplica típicamente a las rutas de invitado (guest). Si el usuario ya
     * ha iniciado sesión con cualquiera de los guards especificados, se registra un evento de
     * depuración en AuthLog con los detalles del usuario y la ruta a la que intentó acceder,
     * y luego se le redirige a su página de inicio personalizada (definida por homeRouteName()).
     * Si no se especifican guards, se evalúa el guard por defecto (null). Si el usuario no está
     * autenticado en ningún guard, la solicitud continúa normalmente.
     *
     * @param  Request  $request  La solicitud HTTP entrante.
     * @param  Closure  $next     Función que delega el procesamiento al siguiente middleware.
     * @param  string   ...$guards Lista de guards de autenticación a verificar (opcional).
     * @return Response            Redirección al dashboard del usuario si está autenticado,
     *                             o respuesta del siguiente middleware si es invitado.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                AuthLog::debug('Authenticated user redirected from guest page', [
                    'event' => AuthLog::EVENT_ROUTE_VISIT,
                    'route' => $request->route()?->getName(),
                    'path' => $request->path(),
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'role' => $user?->role,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Usuario autenticado redirigido desde pagina de invitado.',
                ]);

                return redirect(route($user->homeRouteName()));
            }
        }

        return $next($request);
    }
}
