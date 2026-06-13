<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthLog;
use App\Support\LoginLockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return View
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
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
     * Procesa una solicitud de inicio de sesión.
     *
     * @param LoginRequest $request Solicitud validada que contiene las credenciales del usuario.
     * @return RedirectResponse Redirección al flujo de autenticación multifactor (MFA), área de cliente o panel de administración según corresponda.
     * @throws ValidationException Si las credenciales son incorrectas o la cuenta está configurada únicamente para acceso mediante Google.
     *
     * @see https://docs.phpdoc.org/ Estándar de documentación PHPDoc.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $email = $request->string('email');
        $user = User::where('email', $email)->first();

        if ($user && $user->google_id && !$user->password) {
            AuthLog::warning('Google-only user attempted password login', [
                'event' => AuthLog::EVENT_LOGIN_FAILED,
                'succeeded' => false,
                'email' => (string) $email,
                'user_id' => $user->id,
                'role' => $user->role,
                'guard' => 'web',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Usuario registrado con Google intento login con contrasena.',
            ]);
            throw ValidationException::withMessages([
                'email' => 'Este correo esta registrado con Google. Inicia sesion con Google.',
            ]);
        }

        $request->authenticate();

        $request->session()->regenerate();

        $user = User::where('email', $email)->firstOrFail();

        AuthLog::info('Password factor succeeded', [
            'event' => AuthLog::EVENT_LOGIN_SUCCESS,
            'succeeded' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'guard' => 'web',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Factor de contraseña verificado correctamente.',
        ]);

        $request->session()->put('pending_auth_user_id', $user->id);

        $required = $user->requiredFactors();
        $pending = array_values(array_filter($required, fn($f) => $f !== 'password'));

        $unconfigured = [];
        foreach ($pending as $factor) {
            if ($factor === 'totp' && ! $user->two_factor_enabled) {
                $unconfigured[] = 'totp';
            }
            if ($factor === 'webauthn' && ! $user->webAuthnCredentials()->exists()) {
                $unconfigured[] = 'webauthn';
            }
        }

        session(['factors_required' => $pending, 'factors_passed' => []]);

        if (! empty($unconfigured)) {
            if (in_array('totp', $unconfigured)) {
                AuthLog::info('Redirecting to TOTP setup', [
                    'event' => AuthLog::EVENT_MFA_REDIRECT,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'factor' => 'totp_setup',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Redirigiendo a configuracion TOTP.',
                ]);
                return redirect()->route('mfa.setup');
            }
            if (in_array('webauthn', $unconfigured)) {
                AuthLog::info('Redirecting to WebAuthn setup', [
                    'event' => AuthLog::EVENT_MFA_REDIRECT,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'factor' => 'webauthn_setup',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Redirigiendo a configuracion WebAuthn.',
                ]);
                return redirect()->route('mfa.webauthn.setup');
            }
        }

        if (! empty($pending)) {
            if ($pending[0] === 'totp') {
                AuthLog::info('Redirecting to TOTP verify', [
                    'event' => AuthLog::EVENT_MFA_REDIRECT,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'factor' => 'totp_verify',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Redirigiendo a verificacion TOTP.',
                ]);
                return redirect()->route('mfa.verify');
            }
            if ($pending[0] === 'webauthn') {
                AuthLog::info('Redirecting to WebAuthn auth', [
                    'event' => AuthLog::EVENT_MFA_REDIRECT,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'factor' => 'webauthn_auth',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Redirigiendo a autenticacion WebAuthn.',
                ]);
                return redirect()->route('mfa.webauthn.auth');
            }
        }

        Auth::loginUsingId($user->id, $request->session()->pull('pending_auth_remember', false));
        $request->session()->forget('pending_auth_user_id');

        AuthLog::info('Full login completed (no MFA required)', [
            'event' => AuthLog::EVENT_LOGIN_FULL,
            'succeeded' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'guard' => 'web',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Inicio de sesion completo sin MFA.',
        ]);

        return redirect()->intended(route($request->user()->homeRouteName()));
    }

    /**
     * Log the user out of the application.
     *
     * @param  Request  $request  The current HTTP request.
     * @return RedirectResponse  Redirect to the home page.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->session()->forget([
            'two_factor_passed', 'factors_required', 'factors_passed',
            'mfa_secret', 'pending_auth_user_id', 'pending_auth_remember',
            'webauthn_challenge',
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        AuthLog::info('User logged out', [
            'event' => AuthLog::EVENT_LOGOUT,
            'succeeded' => true,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'role' => $user?->role,
            'guard' => 'web',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Sesion cerrada exitosamente.',
        ]);

        return redirect('/');
    }

}
