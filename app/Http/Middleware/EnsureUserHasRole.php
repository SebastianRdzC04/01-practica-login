<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            Log::warning('Unauthorized role access attempt', [
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'allowed_roles' => $roles,
                'ip_address' => $request->ip(),
            ]);

            abort(403);
        }

        return $next($request);
    }
}
