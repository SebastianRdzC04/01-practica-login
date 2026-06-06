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
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
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
