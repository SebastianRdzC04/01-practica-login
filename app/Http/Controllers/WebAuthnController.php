<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\WebAuthnCredential;

class WebAuthnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function ensureAdminAndTotpPassed(Request $request)
    {
        $user = Auth::user();
        if (! $user || $user->role !== App\Models\User::ROLE_ADMIN) {
            abort(403);
        }

        $passed = $request->session()->get('factors_passed', []);
        if (! in_array('totp', $passed)) {
            return redirect()->route('mfa.verify');
        }
        return null;
    }

    public function showSetup(Request $request)
    {
        if ($resp = $this->ensureAdminAndTotpPassed($request)) {
            return $resp;
        }

        return view('mfa.webauthn_setup');
    }

    public function options(Request $request)
    {
        if ($resp = $this->ensureAdminAndTotpPassed($request)) {
            return $resp;
        }

        // If laragear/webauthn is installed, prefer its helper services.
        if (! class_exists(\Laragear\WebAuthn\WebAuthn::class) && ! app()->bound('webauthn')) {
            return response()->json(['error' => 'webauthn-not-installed','message' => 'Instala el paquete laragear/webauthn con: composer require laragear/webauthn'], 501);
        }

        // Generate a lightweight publicKey creation options structure for the client.
        $user = Auth::user();

        $challenge = random_bytes(32);
        $request->session()->put('webauthn_challenge', base64_encode($challenge));

        $publicKey = [
            'challenge' => base64_encode($challenge),
            'rp' => ['name' => config('app.name')],
            'user' => [
                'id' => base64_encode((string) $user->id),
                'name' => $user->email,
                'displayName' => $user->name ?? $user->email,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7], // ES256
            ],
            'timeout' => 60000,
            'attestation' => 'direct',
        ];

        return response()->json(['publicKey' => $publicKey]);
    }

    public function register(Request $request)
    {
        if ($resp = $this->ensureAdminAndTotpPassed($request)) {
            return $resp;
        }

        if (! class_exists(\Laragear\WebAuthn\WebAuthn::class) && ! app()->bound('webauthn')) {
            return response()->json(['error' => 'webauthn-not-installed','message' => 'Instala el paquete laragear/webauthn con: composer require laragear/webauthn'], 501);
        }

        // The client sends the raw credential response; delegate verification to the package if available.
        try {
            $payload = $request->input();

            // Delegate to package/service if bound
            if (app()->bound('webauthn')) {
                $service = app('webauthn');
                // expected API: register/finishRegistration - adapt if needed after installing package
                $result = $service->finishRegistration($payload);
                // $result expected to contain credential id, public key and sign count
                $credentialId = $result['credential_id'] ?? null;
                $publicKey = $result['public_key'] ?? null;
                $signCount = $result['sign_count'] ?? 0;
            } else {
                // Fallback: store raw fields (not secure). Prefer installing the package.
                $credentialId = $payload['id'] ?? null;
                $publicKey = $payload['publicKey'] ?? null;
                $signCount = 0;
            }

            if (! $credentialId || ! $publicKey) {
                return response()->json(['error' => 'invalid_payload'], 422);
            }

            $cred = WebAuthnCredential::create([
                'user_id' => Auth::id(),
                'name' => $request->input('name') ?? 'Windows Hello',
                'credential_id' => is_string($credentialId) ? $credentialId : json_encode($credentialId),
                'public_key' => is_string($publicKey) ? $publicKey : json_encode($publicKey),
                'sign_count' => $signCount,
                'transports' => $request->input('transports') ?? null,
            ]);

            return response()->json(['ok' => true, 'credential' => $cred]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'exception','message' => $e->getMessage()], 500);
        }
    }

    public function showAuthenticate(Request $request)
    {
        if ($resp = $this->ensureAdminAndTotpPassed($request)) {
            return $resp;
        }

        return view('mfa.webauthn_auth');
    }

    public function assertionOptions(Request $request)
    {
        if ($resp = $this->ensureAdminAndTotpPassed($request)) {
            return $resp;
        }

        $user = Auth::user();
        $creds = $user->webauthnCredentials()->get();

        $allowCredentials = $creds->map(function ($c) {
            return [
                'type' => 'public-key',
                'id' => $c->credential_id,
            ];
        })->all();

        $challenge = random_bytes(32);
        $request->session()->put('webauthn_challenge', base64_encode($challenge));

        $publicKey = [
            'challenge' => base64_encode($challenge),
            'timeout' => 60000,
            'allowCredentials' => $allowCredentials,
            'userVerification' => 'preferred',
        ];

        return response()->json(['publicKey' => $publicKey]);
    }

    public function authenticate(Request $request)
    {
        if ($resp = $this->ensureAdminAndTotpPassed($request)) {
            return $resp;
        }

        if (! class_exists(\Laragear\WebAuthn\WebAuthn::class) && ! app()->bound('webauthn')) {
            return response()->json(['error' => 'webauthn-not-installed','message' => 'Instala el paquete laragear/webauthn con: composer require laragear/webauthn'], 501);
        }

        try {
            $payload = $request->input();

            if (app()->bound('webauthn')) {
                $service = app('webauthn');
                $result = $service->finishAuthentication($payload);
                if (! ($result['verified'] ?? false)) {
                    return response()->json(['error' => 'not_verified'], 422);
                }
            } else {
                // Without the package we cannot securely verify assertions
                return response()->json(['error' => 'webauthn-not-installed'], 501);
            }

            $factorsPassed = $request->session()->get('factors_passed', []);
            $factorsPassed[] = 'webauthn';
            $request->session()->put('factors_passed', array_unique($factorsPassed));

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'exception','message' => $e->getMessage()], 500);
        }
    }
}
