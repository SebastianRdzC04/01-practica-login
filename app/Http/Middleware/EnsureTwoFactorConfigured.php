<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureTwoFactorConfigured
{
    /**
     * Verifica que el usuario autenticado tenga configurados los factores de autenticación multifactor requeridos.
     *
     * Este middleware se ejecuta en cada solicitud entrante para validar que el usuario haya
     * completado la configuración de todos los factores MFA obligatorios definidos en el modelo
     * (TOTP, WebAuthn, etc.). Si el usuario carece de algún factor requerido, es redirigido a la
     * página de configuración correspondiente. Además, si ya existe una sesión con factores
     * pendientes de verificación, redirige al flujo de verificación MFA. Las rutas bajo el prefijo
     * 'mfa.*' quedan exentas de estas redirecciones para permitir la configuración y verificación.
     *
     * @param  Request  $request  La solicitud HTTP entrante.
     * @param  Closure  $next     Función que delega el procesamiento al siguiente middleware.
     * @return \Illuminate\Http\RedirectResponse|mixed  Redirección a configuración/verificación MFA
     *                                                  o continúa con el siguiente middleware.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
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
