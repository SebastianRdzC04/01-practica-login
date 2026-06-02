<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\AuthLog;
use App\Support\LoginLockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        $email = (string) request()->old('email', request()->query('email', ''));

        AuthLog::info('Login screen viewed', [
            'event' => AuthLog::EVENT_LOGIN_SCREEN_VIEWED,
            'succeeded' => true,
            'email' => $email !== '' ? $email : null,
            'guard' => 'web',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'message' => 'Pantalla de inicio de sesion mostrada.',
        ]);

        return view('auth.login', [
            'loginLockout' => LoginLockout::state($email, (string) request()->ip()),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // attempt authentication first
        $request->authenticate();

        // regenerate session to prevent fixation
        $request->session()->regenerate();

        $user = Auth::user();

        // determine required factors for this user
        $required = $user->requiredFactors();

        // always remove 'password' because it was already provided
        $pending = array_values(array_filter($required, fn($f) => $f !== 'password'));

        // check for unconfigured required factors
        $unconfigured = [];
        foreach ($pending as $factor) {
            if ($factor === 'totp' && ! $user->two_factor_enabled) {
                $unconfigured[] = 'totp';
            }
            // añadir comprobaciones para otros factores (webauthn, etc.)
        }

        if (! empty($unconfigured)) {
            // pedir configuracion del primer factor no configurado
            return redirect()->route('mfa.setup');
        }

        // almacenar en sesión los factores pendientes (a verificar)
        session(['factors_required' => $pending, 'factors_passed' => []]);

        // si hay factores pendientes, redirigir al flujo del primer factor
        if (! empty($pending)) {
            if ($pending[0] === 'totp') {
                return redirect()->route('mfa.verify');
            }
            // manejar otros factores según tu diseño
        }

        // no hay factores adicionales — completar login
        return redirect()->intended(route($request->user()->homeRouteName()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['two_factor_passed','factors_required','factors_passed','mfa_secret']);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
