<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureTwoFactorConfigured
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user) {
            $required = method_exists($user, 'requiredFactors') ? $user->requiredFactors() : ['totp'];
            $pendingRequired = array_values(array_filter($required, fn($r) => $r !== 'password'));

            if (empty($pendingRequired)) {
                return $next($request);
            }

            $hasTotp = (bool) $user->two_factor_enabled;
            $isMfaRoute = $request->routeIs('mfa.*');
            $passed = $request->session()->get('factors_passed', []);

            if (! $isMfaRoute) {
                if (in_array('totp', $required) && ! $hasTotp) {
                    return redirect()->route('mfa.setup');
                }

                if (in_array('webauthn', $required) && ! $user->webAuthnCredentials()->exists()) {
                    return redirect()->route('mfa.webauthn.setup');
                }
            }

            if (! empty($pendingRequired) && count(array_intersect($pendingRequired, $passed)) < count($pendingRequired) && ! $isMfaRoute) {
                if (in_array('totp', $pendingRequired) && ! in_array('totp', $passed)) {
                    return redirect()->route('mfa.verify');
                }
                if (in_array('webauthn', $pendingRequired) && ! in_array('webauthn', $passed)) {
                    return redirect()->route('mfa.webauthn.auth');
                }
            }
        }

        return $next($request);
    }
}
