<?php

namespace App\Http\Middleware;

use App\Support\AuthLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRouteVisit
{
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
