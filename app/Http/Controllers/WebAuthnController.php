<?php

namespace App\Http\Controllers;

use App\Services\RecaptchaService;
use App\Support\AuthLog;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidator;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;
use Laragear\WebAuthn\JsonTransport;

class WebAuthnController extends Controller
{
    /**
     * Verifica que el usuario haya superado TOTP antes de WebAuthn.
     *
     * Asegura que el flujo MFA se ejecute en orden: primero TOTP, luego
     * WebAuthn. Si TOTP ya fue superado o el usuario no lo tiene habilitado
     * y no hay autenticación pendiente, retorna null para continuar.
     * En caso contrario, redirige a la verificación TOTP.
     *
     * @param  Request $request Solicitud HTTP entrante.
     * @return mixed|null|RedirectResponse Null si TOTP está superado,
     *         RedirectResponse si debe verificar TOTP primero.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    protected function ensureTotpPassed(Request $request): mixed
    {
        $passed = $request->session()->get('factors_passed', []);
        if (in_array('totp', $passed)) {
            return null;
        }

        $user = Auth::user();
        if ($user && $user->two_factor_enabled && ! $request->session()->has('pending_auth_user_id')) {
            $request->session()->put('factors_passed', ['totp']);
            return null;
        }

        return redirect()->route('mfa.verify');
    }

    /**
     * Muestra la página de configuración WebAuthn.
     *
     * Verifica que TOTP esté superado, luego renderiza la página donde el
     * usuario registra su dispositivo biométrico (huella, Face ID, Windows
     * Hello, etc.). Registra un evento de auditoría al mostrar la página.
     *
     * @param  Request $request Solicitud HTTP entrante.
     * @return RedirectResponse|View Redirección a TOTP o vista de configuración.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function showSetup(Request $request)
    {
        if ($resp = $this->ensureTotpPassed($request)) {
            return $resp;
        }

        $user = Auth::user();

        AuthLog::info('WebAuthn setup page viewed', [
            'event' => AuthLog::EVENT_WEBAUTHN_SETUP_VIEW,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'role' => $user?->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Pantalla de configuracion WebAuthn mostrada.',
        ]);

        return view('mfa.webauthn_setup');
    }

    /**
     * Genera las opciones de registro WebAuthn (attestation).
     *
     * Prepara los parámetros de registro del autenticador WebAuthn:
     * autenticación de plataforma, verificación de usuario requerida y
     * tiempo de espera. Devuelve estos parámetros como JSON para que
     * el navegador inicie el registro de la credencial.
     *
     * @param  AttestationRequest $request Solicitud de attestation WebAuthn.
     * @return JsonResponse Respuesta JSON con las opciones de registro.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function options(AttestationRequest $request)
    {
        if ($resp = $this->ensureTotpPassed($request)) {
            return $resp;
        }

        $request->secureRegistration();

        $json = $request->toCreate();

        $data = $json->toArray();
        $data['authenticatorSelection']['authenticatorAttachment'] = 'platform';
        $data['authenticatorSelection']['userVerification'] = 'required';
        $data['timeout'] = 120000;

        return response()->json(['publicKey' => $data]);
    }

    /**
     * Guarda una nueva credencial WebAuthn registrada por el usuario.
     *
     * Procesa la respuesta de attestation del navegador, detecta el alias
     * del dispositivo según el user agent y guarda la credencial asociada
     * al usuario. Marca WebAuthn como factor superado. Si todos los factores
     * requeridos están completos, autentica al usuario completamente.
     *
     * @param  AttestedRequest $request Solicitud con la credencial atestiguada.
     * @return JsonResponse Respuesta JSON indicando éxito o error.
     *
     * @throws \Exception Si falla el registro de la credencial.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function register(AttestedRequest $request)
    {
        if ($resp = $this->ensureTotpPassed($request)) {
            return $resp;
        }

        $recaptchaToken = $request->input('g-recaptcha-response');
        $recaptchaSecret = config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET');
        if ($recaptchaSecret) {
            if (!RecaptchaService::verify($recaptchaToken, $request->ip())) {
                Log::warning('reCAPTCHA failed on WebAuthn register', ['ip' => $request->ip()]);
                return response()->json(['error' => 'captcha_failed', 'message' => __('reCAPTCHA verification failed.')], 422);
            }
        }

        $user = Auth::user();
        $alias = $this->detectDeviceAlias($request);

        try {
            $request->save(['alias' => $alias]);

            AuthLog::info('WebAuthn credential registered', [
                'event' => AuthLog::EVENT_WEBAUTHN_REGISTER,
                'succeeded' => true,
                'user_id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'device_alias' => $alias,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Credencial WebAuthn registrada exitosamente.',
            ]);
        } catch (\Exception $e) {
            AuthLog::error('WebAuthn registration failed', [
                'event' => AuthLog::EVENT_WEBAUTHN_REGISTER_FAILED,
                'succeeded' => false,
                'user_id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Fallo el registro de credencial WebAuthn: ' . $e->getMessage(),
            ]);
            throw $e;
        }

        $factorsPassed = $request->session()->get('factors_passed', []);
        $factorsPassed[] = 'webauthn';
        $request->session()->put('factors_passed', array_unique($factorsPassed));

        $required = $request->session()->get('factors_required', []);
        $passed = $request->session()->get('factors_passed', []);

        if (empty($required) || count(array_intersect($required, $passed)) >= count($required)) {
            $pendingId = $request->session()->pull('pending_auth_user_id');
            $remember = $request->session()->pull('pending_auth_remember', false);
            if ($pendingId) {
                Auth::loginUsingId($pendingId, $remember);

                AuthLog::info('Full login completed after WebAuthn register', [
                    'event' => AuthLog::EVENT_LOGIN_FULL,
                    'succeeded' => true,
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'role' => $user?->role,
                    'guard' => 'web',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Inicio de sesion completo tras registrar WebAuthn.',
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Muestra la página de autenticación WebAuthn.
     *
     * Verifica que TOTP esté superado, luego renderiza la página donde el
     * usuario se autentica usando su dispositivo biométrico registrado.
     * Registra un evento de auditoría al mostrar la página.
     *
     * @param  Request $request Solicitud HTTP entrante.
     * @return RedirectResponse|View Redirección a TOTP o vista de autenticación.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function showAuthenticate(Request $request)
    {
        if ($resp = $this->ensureTotpPassed($request)) {
            return $resp;
        }

        $user = Auth::user();

        AuthLog::info('WebAuthn authenticate page viewed', [
            'event' => AuthLog::EVENT_WEBAUTHN_AUTH_VIEW,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'role' => $user?->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Pantalla de autenticacion WebAuthn mostrada.',
        ]);

        return view('mfa.webauthn_auth');
    }

    /**
     * Genera las opciones de autenticación WebAuthn (assertion).
     *
     * Prepara los parámetros para que el navegador inicie la autenticación
     * con una credencial WebAuthn existente. Configura verificación de
     * usuario y tiempo de espera. Devuelve los parámetros como JSON.
     *
     * @param  AssertionRequest $request Solicitud de assertion WebAuthn.
     * @return JsonResponse Respuesta JSON con las opciones de autenticación.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function assertionOptions(AssertionRequest $request)
    {
        if ($resp = $this->ensureTotpPassed($request)) {
            return $resp;
        }

        $json = $request->toVerify(Auth::user());

        $data = $json->toArray();
        $data['userVerification'] = 'required';
        $data['timeout'] = 120000;

        return response()->json(['publicKey' => $data]);
    }

    /**
     * Autentica al usuario mediante una credencial WebAuthn.
     *
     * Aplica limitación de tasa contra fuerza bruta, valida la aserción
     * WebAuthn usando el validador de Laragear y, si es exitosa, marca
     * WebAuthn como factor superado. Si todos los factores requeridos
     * están completos, autentica al usuario completamente.
     * En caso de error, incrementa el contador de intentos y devuelve
     * un mensaje de error JSON con el código HTTP correspondiente.
     *
     * @param  AssertedRequest $request Solicitud con la aserción WebAuthn.
     * @return JsonResponse Respuesta JSON (200 éxito, 422 error, 429 límite).
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function authenticate(AssertedRequest $request)
    {
        $user = Auth::user();

        $recaptchaToken = $request->input('g-recaptcha-response');
        $recaptchaSecret = config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET');
        if ($recaptchaSecret) {
            if (!RecaptchaService::verify($recaptchaToken, $request->ip())) {
                Log::warning('reCAPTCHA failed on WebAuthn authenticate', ['ip' => $request->ip()]);
                return response()->json(['error' => 'captcha_failed', 'message' => __('reCAPTCHA verification failed.')], 422);
            }
        }

        $rateLimitKey = 'mfa-webauthn:' . ($user?->id ?? request()->ip());
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            AuthLog::warning('WebAuthn authentication rate limited', [
                'event' => AuthLog::EVENT_WEBAUTHN_AUTH_FAILED,
                'succeeded' => false,
                'user_id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Demasiados intentos WebAuthn.',
            ]);
            return response()->json([
                'error' => 'rate_limited',
                'message' => 'Demasiados intentos. Intenta de nuevo en ' . ceil($seconds / 60) . ' minutos.',
            ], 429);
        }

        try {
            $validator = app(AssertionValidator::class);
            $transport = new JsonTransport($request->validated());

            $validator->send(new AssertionValidation($transport, $user))->thenReturn();

            RateLimiter::clear($rateLimitKey);

            $factorsPassed = $request->session()->get('factors_passed', []);
            $factorsPassed[] = 'webauthn';
            $request->session()->put('factors_passed', array_unique($factorsPassed));

            AuthLog::info('WebAuthn authentication successful', [
                'event' => AuthLog::EVENT_WEBAUTHN_AUTH,
                'succeeded' => true,
                'user_id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Autenticacion WebAuthn exitosa.',
            ]);

            $required = $request->session()->get('factors_required', []);
            $passed = $request->session()->get('factors_passed', []);

            if (empty($required) || count(array_intersect($required, $passed)) >= count($required)) {
                $pendingId = $request->session()->pull('pending_auth_user_id');
                $remember = $request->session()->pull('pending_auth_remember', false);
                if ($pendingId) {
                    Auth::loginUsingId($pendingId, $remember);

                    AuthLog::info('Full login completed after WebAuthn auth', [
                        'event' => AuthLog::EVENT_LOGIN_FULL,
                        'succeeded' => true,
                        'user_id' => $user?->id,
                        'email' => $user?->email,
                        'role' => $user?->role,
                        'guard' => 'web',
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'message' => 'Inicio de sesion completo tras autenticar WebAuthn.',
                    ]);
                }
            }

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            RateLimiter::hit($rateLimitKey, 60);
            AuthLog::warning('WebAuthn authentication failed', [
                'event' => AuthLog::EVENT_WEBAUTHN_AUTH_FAILED,
                'succeeded' => false,
                'user_id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Fallo la autenticacion WebAuthn: ' . $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'not_verified',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Detecta el alias del dispositivo basado en el user agent.
     *
     * Analiza el user agent para determinar un nombre descriptivo del
     * dispositivo biométrico (Windows Hello, Touch ID, Face ID, huella
     * Android, etc.). Este alias se almacena con la credencial WebAuthn
     * para facilitar su identificación por el usuario.
     *
     * @param  Request $request Solicitud HTTP con el user agent.
     * @return string Alias descriptivo del dispositivo biométrico.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    protected function detectDeviceAlias(Request $request): string
    {
        $ua = $request->userAgent();

        if (Str::contains($ua, ['Windows'])) {
            return 'Windows Hello';
        }

        if (Str::contains($ua, ['Macintosh', 'Mac OS'])) {
            return 'Touch ID';
        }

        if (Str::contains($ua, ['iPhone', 'iPad', 'iPod'])) {
            return 'Face ID / Touch ID';
        }

        if (Str::contains($ua, ['Android'])) {
            return 'Huella / Biometrico Android';
        }

        return 'Dispositivo biometrico';
    }
}
