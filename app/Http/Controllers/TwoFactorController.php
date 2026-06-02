<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

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

        $qrImage = 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl='.urlencode($qrCodeUrl);

        return view('mfa.setup', [
            'qrImage' => $qrImage,
            'secret' => $secret,
        ]);
    }

    public function confirmSetup(Request $request)
    {
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

        // save encrypted secret and mark enabled
        $user->two_factor_secret = encrypt($secret);
        $user->two_factor_enabled = true;
        $user->save();

        // clear session temp secret
        $request->session()->forget('mfa_secret');

        return redirect()->intended('/');
    }
}
