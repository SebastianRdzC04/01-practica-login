<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use App\Services\RecaptchaService;
use Illuminate\Validation\ValidationException;

class TwoFactorController
{
    public function showSetup(Request $request)
    {
        $user = Auth::user();

        $google2fa = new Google2FA();

        // keep secret in session until user confirms
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

    public function confirmSetup(Request $request)
    {
        // Verify reCAPTCHA token
        $token = $request->input('g-recaptcha-response');
        if (config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET')) {
            if (! RecaptchaService::verify($token, $request->ip())) {
                throw ValidationException::withMessages([
                    'totp' => 'reCAPTCHA verification failed.',
                ]);
            }
        }

        $request->validate([
            'totp' => ['required', 'digits:6'],
        ]);

        $user = Auth::user();
        $google2fa = new Google2FA();

        $secret = $request->session()->get('mfa_secret');
        if (! $secret) {
            return redirect()->route('mfa.setup')->withErrors(['totp' => 'Clave temporal no encontrada, vuelve a generar el QR.']);
        }

        $valid = $google2fa->verifyKey($secret, $request->input('totp'));

        if (! $valid) {
            return back()->withErrors(['totp' => 'Código TOTP inválido'])->withInput();
        }

        $user->two_factor_secret = encrypt($secret);
        $user->two_factor_enabled = true;
        $user->save();

        $request->session()->forget('mfa_secret');

        $factorsPassed = $request->session()->get('factors_passed', []);
        $factorsPassed[] = 'totp';
        $request->session()->put('factors_passed', array_unique($factorsPassed));

        $required = $request->session()->get('factors_required', []);

        if (in_array('webauthn', $required) && ! $user->webAuthnCredentials()->exists()) {
            return redirect()->route('mfa.webauthn.setup');
        }

        $pendingId = $request->session()->pull('pending_auth_user_id');
        $remember = $request->session()->pull('pending_auth_remember', false);
        if ($pendingId) {
            Auth::loginUsingId($pendingId, $remember);
        }

        return redirect()->intended(route($request->user()->homeRouteName()));
    }

    public function showVerify(Request $request)
    {
        return view('mfa.verify');
    }
    public function verify(Request $request)
    {
        // Verify reCAPTCHA token
        $token = $request->input('g-recaptcha-response');
        if (config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET')) {
            if (! RecaptchaService::verify($token, $request->ip())) {
                throw ValidationException::withMessages([
                    'totp' => 'reCAPTCHA verification failed.',
                ]);
            }
        }

        $request->validate(['totp' => ['required','digits:6']]);

        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        $secretEncrypted = $user->two_factor_secret;
        if (! $secretEncrypted) {
            return redirect()->route('mfa.setup')->withErrors(['totp' => 'No existe clave TOTP, configura MFA primero.']);
        }

        $secret = decrypt($secretEncrypted);
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $request->input('totp'));

        if (! $valid) {
            return back()->withErrors(['totp' => 'Código TOTP inválido.'])->withInput();
        }

        $factorsPassed = $request->session()->get('factors_passed', []);
        $factorsPassed[] = 'totp';
        $request->session()->put('factors_passed', array_unique($factorsPassed));

        $required = $request->session()->get('factors_required', []);
        $passed = $request->session()->get('factors_passed', []);

        if (empty($required) || count(array_intersect($required, $passed)) >= count($required)) {
            $pendingId = $request->session()->pull('pending_auth_user_id');
            $remember = $request->session()->pull('pending_auth_remember', false);
            if ($pendingId) {
                Auth::loginUsingId($pendingId, $remember);
            }
            return redirect()->intended(route($request->user()->homeRouteName()));
        }

        if (in_array('webauthn', $required) && ! in_array('webauthn', $passed)) {
            $user = Auth::user();
            if ($user && ! $user->webAuthnCredentials()->exists()) {
                return redirect()->route('mfa.webauthn.setup');
            }
            return redirect()->route('mfa.webauthn.auth');
        }

        return redirect()->route('home.redirect');
    }
}
