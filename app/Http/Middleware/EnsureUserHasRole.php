<?php

namespace App\Http\Middleware;

use App\Support\AuthLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
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
