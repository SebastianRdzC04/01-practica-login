<?php

namespace App\Http\Controllers;

use App\Support\AuthLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use App\Services\RecaptchaService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class TwoFactorController
{
    /**
     * Muestra la página de configuración de TOTP (Google Authenticator).
     *
     * Genera una clave secreta TOTP si no existe una en la sesión actual,
     * crea un código QR que el usuario escanea con su aplicación de
     * autenticación y renderiza la vista de configuración.
     * Registra un evento de auditoría al mostrar la página.
     *
     * @param  Request $request Solicitud HTTP entrante.
     * @return View Vista con el código QR y la clave secreta TOTP.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function showSetup(Request $request)
    {
        $user = Auth::user();

        AuthLog::info('TOTP setup page viewed', [
            'event' => AuthLog::EVENT_TOTP_SETUP_VIEW,
            'succeeded' => true,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'role' => $user?->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Pantalla de configuracion TOTP mostrada.',
        ]);

        $google2fa = new Google2FA();

        $secret = $request->session()->get('mfa_secret');
        if (! $secret) {
            $secret = $google2fa->generateSecretKey();
            $request->session()->put('mfa_secret', $secret);
        }

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());
        $writer = new Writer($renderer);
        $svg = $writer->writeString($qrCodeUrl);
        $qrImage = 'data:image/svg+xml;base64,' . base64_encode($svg);

        return view('mfa.setup', [
            'qrImage' => $qrImage,
            'secret' => $secret,
        ]);
    }

    /**
     * Confirma y guarda la configuración TOTP del usuario.
     *
     * Verifica el token de reCAPTCHA, valida el código TOTP de 6 dígitos
     * contra la clave secreta en sesión. Si es válido, guarda la clave cifrada
     * en el usuario y marca TOTP como configurado. Luego verifica si hay más
     * factores MFA pendientes (como WebAuthn) y redirige en consecuencia.
     *
     * @param  Request $request Solicitud HTTP con el código TOTP y reCAPTCHA.
     * @return RedirectResponse|View Redirección a WebAuthn, inicio, o error.
     * @throws ValidationException Si falla la verificación de reCAPTCHA.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function confirmSetup(Request $request)
    {
        $user = Auth::user();

        // Verify reCAPTCHA token
        $token = $request->input('g-recaptcha-response');
        if (config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET')) {
            if (! RecaptchaService::verify($token, $request->ip())) {
                AuthLog::warning('TOTP setup reCAPTCHA failed', [
                    'event' => AuthLog::EVENT_TOTP_SETUP_FAILED,
                    'succeeded' => false,
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'role' => $user?->role,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'reCAPTCHA fallo en configuracion TOTP.',
                ]);
                throw ValidationException::withMessages([
                    'totp' => __('reCAPTCHA verification failed.'),
                ]);
            }
        }

        $request->validate([
            'totp' => ['required', 'digits:6'],
        ]);

        $google2fa = new Google2FA();

        $secret = $request->session()->get('mfa_secret');
        if (! $secret) {
            AuthLog::warning('TOTP setup - session secret missing', [
                'event' => AuthLog::EVENT_TOTP_SETUP_FAILED,
                'succeeded' => false,
                'user_id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Clave temporal TOTP no encontrada en sesion.',
            ]);
            return redirect()->route('mfa.setup')->withErrors(['totp' => 'Clave temporal no encontrada, vuelve a generar el QR.']);
        }

        $valid = $google2fa->verifyKey($secret, $request->input('totp'));

        if (! $valid) {
            AuthLog::warning('TOTP setup - invalid code', [
                'event' => AuthLog::EVENT_TOTP_SETUP_FAILED,
                'succeeded' => false,
                'user_id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Codigo TOTP invalido durante configuracion.',
            ]);
            return back()->withErrors(['totp' => 'Código TOTP inválido'])->withInput();
        }

        $user->two_factor_secret = encrypt($secret);
        $user->two_factor_enabled = true;
        $user->save();

        $request->session()->forget('mfa_secret');

        $factorsPassed = $request->session()->get('factors_passed', []);
        $factorsPassed[] = 'totp';
        $request->session()->put('factors_passed', array_unique($factorsPassed));

        AuthLog::info('TOTP configured successfully', [
            'event' => AuthLog::EVENT_TOTP_SETUP_CONFIRM,
            'succeeded' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'TOTP configurado exitosamente.',
        ]);

        $required = $request->session()->get('factors_required', []);

        if (in_array('webauthn', $required) && ! $user->webAuthnCredentials()->exists()) {
            AuthLog::info('TOTP setup done - redirecting to WebAuthn setup', [
                'event' => AuthLog::EVENT_MFA_REDIRECT,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'factor' => 'webauthn_setup',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Redirigiendo a configuracion WebAuthn tras TOTP.',
            ]);
            return redirect()->route('mfa.webauthn.setup');
        }

        $pendingId = $request->session()->pull('pending_auth_user_id');
        $remember = $request->session()->pull('pending_auth_remember', false);
        if ($pendingId) {
            Auth::loginUsingId($pendingId, $remember);

            AuthLog::info('Full login completed after TOTP setup', [
                'event' => AuthLog::EVENT_LOGIN_FULL,
                'succeeded' => true,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'guard' => 'web',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Inicio de sesion completo tras configurar TOTP.',
            ]);
        }

        return redirect()->intended(route($request->user()->homeRouteName()));
    }

    /**
     * Muestra la página de verificación TOTP.
     *
     * Renderiza el formulario donde el usuario ingresa el código de 6 dígitos
     * de su aplicación de autenticación para completar el segundo factor.
     * Registra un evento de auditoría al mostrar la página.
     *
     * @param  Request $request Solicitud HTTP entrante.
     * @return View Vista del formulario de verificación TOTP.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function showVerify(Request $request)
    {
        $user = Auth::user();

        AuthLog::info('TOTP verify page viewed', [
            'event' => AuthLog::EVENT_TOTP_VERIFY_VIEW,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'role' => $user?->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Pantalla de verificacion TOTP mostrada.',
        ]);

        return view('mfa.verify');
    }

    /**
     * Verifica el código TOTP ingresado por el usuario.
     *
     * Aplica limitación de tasa contra fuerza bruta, verifica reCAPTCHA,
     * valida el código TOTP contra la clave secreta del usuario. Si es
     * válido, marca TOTP como factor superado. Si todos los factores
     * requeridos están completos, autentica al usuario completamente.
     *
     * @param  Request $request Solicitud HTTP con el código TOTP y reCAPTCHA.
     * @return RedirectResponse|View Redirección a inicio, MFA, o error.
     * @throws ValidationException Si falla reCAPTCHA o límite de tasa.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function verify(Request $request)
    {
        $user = Auth::user();

        $rateLimitKey = 'mfa-totp:' . ($user?->id ?? $request->session()->get('pending_auth_user_id', request()->ip()));
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            AuthLog::warning('TOTP verify rate limited', [
                'event' => AuthLog::EVENT_TOTP_VERIFY_FAILED,
                'succeeded' => false,
                'user_id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Demasiados intentos TOTP.',
            ]);
            throw ValidationException::withMessages([
                'totp' => 'Demasiados intentos. Intenta de nuevo en ' . ceil($seconds / 60) . ' minutos.',
            ]);
        }

        // Verify reCAPTCHA token
        $token = $request->input('g-recaptcha-response');
        if (config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET')) {
            if (! RecaptchaService::verify($token, $request->ip())) {
                AuthLog::warning('TOTP verify reCAPTCHA failed', [
                    'event' => AuthLog::EVENT_TOTP_VERIFY_FAILED,
                    'succeeded' => false,
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'role' => $user?->role,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'reCAPTCHA fallo en verificacion TOTP.',
                ]);
                throw ValidationException::withMessages([
                    'totp' => __('reCAPTCHA verification failed.'),
                ]);
            }
        }

        $request->validate(['totp' => ['required','digits:6']]);

        if (! $user) {
            AuthLog::warning('TOTP verify - no user in session', [
                'event' => AuthLog::EVENT_TOTP_VERIFY_FAILED,
                'succeeded' => false,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'No hay usuario en sesion para verificar TOTP.',
            ]);
            return redirect()->route('login');
        }

        $secretEncrypted = $user->two_factor_secret;
        if (! $secretEncrypted) {
            AuthLog::warning('TOTP verify - no secret configured', [
                'event' => AuthLog::EVENT_TOTP_VERIFY_FAILED,
                'succeeded' => false,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'No existe clave TOTP configurada para el usuario.',
            ]);
            return redirect()->route('mfa.setup')->withErrors(['totp' => 'No existe clave TOTP, configura MFA primero.']);
        }

        $secret = decrypt($secretEncrypted);
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $request->input('totp'));

        if (! $valid) {
            RateLimiter::hit($rateLimitKey, 60);
            AuthLog::warning('TOTP verify - invalid code', [
                'event' => AuthLog::EVENT_TOTP_VERIFY_FAILED,
                'succeeded' => false,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Codigo TOTP invalido durante verificacion.',
            ]);
            return back()->withErrors(['totp' => 'Código TOTP inválido.'])->withInput();
        }

        RateLimiter::clear($rateLimitKey);

        $factorsPassed = $request->session()->get('factors_passed', []);
        $factorsPassed[] = 'totp';
        $request->session()->put('factors_passed', array_unique($factorsPassed));

        AuthLog::info('TOTP verified successfully', [
            'event' => AuthLog::EVENT_TOTP_VERIFY,
            'succeeded' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Verificacion TOTP exitosa.',
        ]);

        $required = $request->session()->get('factors_required', []);
        $passed = $request->session()->get('factors_passed', []);

        if (empty($required) || count(array_intersect($required, $passed)) >= count($required)) {
            $pendingId = $request->session()->pull('pending_auth_user_id');
            $remember = $request->session()->pull('pending_auth_remember', false);
            if ($pendingId) {
                Auth::loginUsingId($pendingId, $remember);

                AuthLog::info('Full login completed after TOTP verify', [
                    'event' => AuthLog::EVENT_LOGIN_FULL,
                    'succeeded' => true,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'guard' => 'web',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Inicio de sesion completo tras verificar TOTP.',
                ]);
            }
            return redirect()->intended(route($request->user()->homeRouteName()));
        }

        if (in_array('webauthn', $required) && ! in_array('webauthn', $passed)) {
            $user = Auth::user();
            if ($user && ! $user->webAuthnCredentials()->exists()) {
                AuthLog::info('TOTP verified - redirecting to WebAuthn setup', [
                    'event' => AuthLog::EVENT_MFA_REDIRECT,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'factor' => 'webauthn_setup',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Redirigiendo a configuracion WebAuthn tras TOTP.',
                ]);
                return redirect()->route('mfa.webauthn.setup');
            }
            AuthLog::info('TOTP verified - redirecting to WebAuthn auth', [
                'event' => AuthLog::EVENT_MFA_REDIRECT,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'factor' => 'webauthn_auth',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Redirigiendo a autenticacion WebAuthn tras TOTP.',
            ]);
            return redirect()->route('mfa.webauthn.auth');
        }

        return redirect()->route('home.redirect');
    }
}
