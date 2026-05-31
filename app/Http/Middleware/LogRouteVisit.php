<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRouteVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $user = $request->user();

            Log::info('Protected view visited', [
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'ip_address' => $request->ip(),
            ]);
        }

        return $response;
    }
}
