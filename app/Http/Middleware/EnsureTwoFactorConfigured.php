<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class EnsureTwoFactorConfigured
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user) {
            // only enforce for admin and usuario roles
            if (in_array($user->role, [User::ROLE_USER, User::ROLE_ADMIN])) {
                $has = (bool) $user->two_factor_enabled || (bool) $user->two_factor_secret;

                $isMfaRoute = $request->routeIs('mfa.*');

                if (! $has && ! $isMfaRoute) {
                    return redirect()->route('mfa.setup');
                }
            }
        }

        return $next($request);
    }
}
