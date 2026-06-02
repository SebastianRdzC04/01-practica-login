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
                if (! $isMfaRoute) {
                    if (in_array('totp', $required) && ! $hasTotp) {
                        return redirect()->route('mfa.setup');
                    }

                    // webauthn: si está requerido pero no tiene credenciales, pedir setup (solo para admin)
                    if (in_array('webauthn', $required) && $user->role === User::ROLE_ADMIN && ! $user->webauthnCredentials()->exists()) {
                        return redirect()->route('mfa.webauthn.setup');
                    }
                }

                // si ya configurado pero no ha pasado la verificación completa
                $pendingRequired = array_filter($required, fn($r) => $r !== 'password');
                if (! empty($pendingRequired) && count(array_intersect($pendingRequired, $passed)) < count($pendingRequired) && ! $isMfaRoute) {
                    // redirigir al primer factor pendiente
                    if (in_array('totp', $pendingRequired) && ! in_array('totp', $passed)) {
                        return redirect()->route('mfa.verify');
                    }
                    if (in_array('webauthn', $pendingRequired) && ! in_array('webauthn', $passed) && $user->role === User::ROLE_ADMIN) {
                        return redirect()->route('mfa.webauthn.auth');
                    }
                }
            }
        }

        return $next($request);
    }
}
