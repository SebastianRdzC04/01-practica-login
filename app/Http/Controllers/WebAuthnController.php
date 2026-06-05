<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $data['timeout'] = 120000;

        return response()->json(['publicKey' => $data]);
    }

    public function register(AttestedRequest $request)
    {
        if ($resp = $this->ensureTotpPassed($request)) {
            return $resp;
        }

        $request->save(['alias' => 'Windows Hello']);

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
}
