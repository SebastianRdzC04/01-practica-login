<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (! in_array('totp', $passed)) {
            return redirect()->route('mfa.verify');
        }
        return null;
    }

    public function showSetup(Request $request)
    {
        if ($resp = $this->ensureTotpPassed($request)) {
            return $resp;
        }

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

        $request->save(['alias' => $this->detectDeviceAlias($request)]);

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
            }
        }

        return response()->json(['ok' => true]);
    }

    public function showAuthenticate(Request $request)
    {
        if ($resp = $this->ensureTotpPassed($request)) {
            return $resp;
        }

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
        try {
            $user = Auth::user();

            $validator = app(AssertionValidator::class);
            $transport = new JsonTransport($request->validated());

            $validator->send(new AssertionValidation($transport, $user))->thenReturn();

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
                }
            }

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
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
