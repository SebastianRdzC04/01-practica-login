<?php

namespace App\Http\Controllers;

use App\Support\AuthLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function register(AttestedRequest $request)
    {
        if ($resp = $this->ensureTotpPassed($request)) {
            return $resp;
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

    public function authenticate(AssertedRequest $request)
    {
        $user = Auth::user();

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
