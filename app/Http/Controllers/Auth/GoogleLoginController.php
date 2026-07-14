<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleLoginController extends Controller
{
    /**
     * Redirige al usuario a Google OAuth para autenticación.
     *
     * Inicia el flujo de autenticación con Google mediante Socialite.
     * Configura la URL de retorno dinámicamente según el esquema y host
     * de la solicitud actual. Registra un evento de auditoría antes de
     * redirigir al usuario a la página de consentimiento de Google.
     *
     * @param  Request $request Solicitud HTTP entrante.
     * @return RedirectResponse Redirección a Google OAuth.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function redirect(Request $request): RedirectResponse
    {
        AuthLog::info('Google login redirect', [
            'event' => AuthLog::EVENT_GOOGLE_REDIRECT,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Redireccionando a Google OAuth.',
        ]);

        $redirectUrl = config('services.google.redirect') ?? ($request->getSchemeAndHttpHost() . '/auth/google/callback');

        return Socialite::driver('google')
            ->redirectUrl($redirectUrl)
            ->redirect();
    }

    /**
     * Procesa el callback de Google OAuth después de la autenticación.
     *
     * Recibe el código de autorización de Google, obtiene los datos del
     * usuario y determina si debe iniciar sesión (usuario existente) o crear
     * una cuenta nueva. Verifica si el email ya está registrado con contraseña
     * para evitar duplicados. Maneja la autenticación multifactor (MFA)
     * redirigiendo al usuario a configurar o verificar TOTP o WebAuthn.
     * Registra eventos de auditoría detallados en cada etapa del proceso.
     *
     * @param  Request $request Solicitud HTTP con el código de autorización.
     * @return RedirectResponse Redirección a la ruta de inicio o a MFA.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $redirectUrl = config('services.google.redirect') ?? ($request->getSchemeAndHttpHost() . '/auth/google/callback');

            $googleUser = Socialite::driver('google')
                ->redirectUrl($redirectUrl)
                ->user();
        } catch (\Exception $e) {
            AuthLog::error('Google login error', [
                'event' => AuthLog::EVENT_GOOGLE_ERROR,
                'succeeded' => false,
                'exception' => get_class($e),
                'message' => 'Error en callback de Google: ' . ($e->getMessage() ?: get_class($e)),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $message = 'Error al autenticar con Google. Intenta de nuevo.';
            if (config('app.debug')) {
                $debug = $e->getMessage() ?: get_class($e);
                $message .= ' (' . $debug . ')';
            }

            return redirect()->route('login')->withErrors([
                'email' => $message,
            ]);
        }

        $existing = User::where('email', $googleUser->getEmail())->first();

        if ($existing && !$existing->google_id) {
            AuthLog::warning('Google login blocked - user has password', [
                'event' => AuthLog::EVENT_GOOGLE_BLOCKED,
                'succeeded' => false,
                'email' => $googleUser->getEmail(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Usuario con email existente intento login con Google pero tiene contrasena.',
                'google_id' => $googleUser->getId(),
                'google_name' => $googleUser->getName(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'Este correo ya esta registrado con una contrasena. Inicia sesion con tu contrasena.',
            ]);
        }

        if ($existing) {
            $user = $existing;

            AuthLog::info('Google login success (existing user)', [
                'event' => AuthLog::EVENT_GOOGLE_SUCCESS,
                'succeeded' => true,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Inicio de sesion con Google exitoso (usuario existente).',
            ]);
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? explode('@', $googleUser->getEmail())[0],
                'email' => $googleUser->getEmail(),
                'role' => User::ROLE_CLIENT,
                'google_id' => $googleUser->getId(),
                'google_avatar' => $googleUser->getAvatar(),
                'password' => null,
            ]);

            AuthLog::info('Google login - new user created', [
                'event' => AuthLog::EVENT_GOOGLE_NEW_USER,
                'succeeded' => true,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'google_id' => $googleUser->getId(),
                'google_name' => $googleUser->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Nuevo usuario creado via Google OAuth.',
            ]);
        }

        $request->session()->put('pending_auth_user_id', $user->id);

        $required = $user->requiredFactors();
        $pending = array_values(array_filter($required, fn($f) => $f !== 'password'));

        $unconfigured = [];
        foreach ($pending as $factor) {
            if ($factor === 'totp' && !$user->two_factor_enabled) {
                $unconfigured[] = 'totp';
            }
            if ($factor === 'webauthn' && !$user->webAuthnCredentials()->exists()) {
                $unconfigured[] = 'webauthn';
            }
        }

        session(['factors_required' => $pending, 'factors_passed' => []]);

        if (!empty($unconfigured)) {
            if (in_array('totp', $unconfigured)) {
                AuthLog::info('Google login - redirecting to TOTP setup', [
                    'event' => AuthLog::EVENT_MFA_REDIRECT,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'factor' => 'totp_setup',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Redirigiendo a configuracion TOTP post-Google.',
                ]);
                return redirect()->route('mfa.setup');
            }
            if (in_array('webauthn', $unconfigured)) {
                AuthLog::info('Google login - redirecting to WebAuthn setup', [
                    'event' => AuthLog::EVENT_MFA_REDIRECT,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'factor' => 'webauthn_setup',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Redirigiendo a configuracion WebAuthn post-Google.',
                ]);
                return redirect()->route('mfa.webauthn.setup');
            }
        }

        if (!empty($pending)) {
            if ($pending[0] === 'totp') {
                AuthLog::info('Google login - redirecting to TOTP verify', [
                    'event' => AuthLog::EVENT_MFA_REDIRECT,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'factor' => 'totp_verify',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Redirigiendo a verificacion TOTP post-Google.',
                ]);
                return redirect()->route('mfa.verify');
            }
            if ($pending[0] === 'webauthn') {
                AuthLog::info('Google login - redirecting to WebAuthn auth', [
                    'event' => AuthLog::EVENT_MFA_REDIRECT,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'factor' => 'webauthn_auth',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Redirigiendo a autenticacion WebAuthn post-Google.',
                ]);
                return redirect()->route('mfa.webauthn.auth');
            }
        }

        $pendingId = $request->session()->pull('pending_auth_user_id');
        if ($pendingId) {
            Auth::loginUsingId($pendingId);
        }
        $request->session()->forget('pending_auth_user_id');

        AuthLog::info('Google login fully completed', [
            'event' => AuthLog::EVENT_LOGIN_FULL,
            'succeeded' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'guard' => 'web',
            'auth_method' => 'google',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Inicio de sesion con Google completado.',
        ]);

        return redirect()->intended(route($user->homeRouteName()));
    }
}
