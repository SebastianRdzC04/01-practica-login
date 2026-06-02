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
                $required = method_exists($user, 'requiredFactors') ? $user->requiredFactors() : ['totp'];
                $hasTotp = (bool) $user->two_factor_enabled;
                $isMfaRoute = $request->routeIs('mfa.*');
                $passed = $request->session()->get('factors_passed', []);

                // si falta configurar un factor obligatorio -> enviar a setup
                if (in_array('totp', $required) && ! $hasTotp && ! $isMfaRoute) {
                    return redirect()->route('mfa.setup');
                }

                // si ya configurado pero no ha pasado la verificación completa
                $pendingRequired = array_filter($required, fn($r) => $r !== 'password');
                if (! empty($pendingRequired) && count(array_intersect($pendingRequired, $passed)) < count($pendingRequired) && ! $isMfaRoute) {
                    // redirigir al primer factor pendiente
                    if (in_array('totp', $pendingRequired) && ! in_array('totp', $passed)) {
                        return redirect()->route('mfa.verify');
                    }
                    // manejar otros factores...
                }
            }
        }

        return $next($request);
    }
}
