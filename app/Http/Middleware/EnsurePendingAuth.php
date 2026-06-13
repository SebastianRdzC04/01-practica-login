<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePendingAuth
{
    /**
     * Verifica si existe una sesión de autenticación pendiente y completa el inicio de sesión.
     *
     * Este middleware se utiliza durante el flujo de autenticación en varios pasos (por ejemplo,
     * después de verificar el correo electrónico o completar el registro). Si el usuario ya está
     * autenticado completamente, permite el paso. Si existe un identificador de usuario pendiente
     * en la sesión ('pending_auth_user_id'), lo autentica de forma temporal con Auth::onceUsingId()
     * para que la solicitud actual pueda procesarse. En caso contrario, redirige a la página de
     * inicio de sesión.
     *
     * @param  Request  $request  La solicitud HTTP entrante.
     * @param  Closure  $next     Función que delega el procesamiento al siguiente middleware.
     * @return \Illuminate\Http\RedirectResponse|mixed  Redirección al login o continúa con la solicitud.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            return $next($request);
        }

        $pendingUserId = $request->session()->get('pending_auth_user_id');
        if ($pendingUserId) {
            Auth::onceUsingId($pendingUserId);
            return $next($request);
        }

        return redirect()->route('login');
    }
}
