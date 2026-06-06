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
    public function redirect(Request $request): RedirectResponse
    {
        $redirectUrl = $request->getSchemeAndHttpHost() . '/auth/google/callback';

        return Socialite::driver('google')
            ->redirectUrl($redirectUrl)
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $redirectUrl = $request->getSchemeAndHttpHost() . '/auth/google/callback';

            $googleUser = Socialite::driver('google')
                ->redirectUrl($redirectUrl)
                ->user();
        } catch (\Exception $e) {
            logger()->error('Google login error: ' . ($e->getMessage() ?: get_class($e)), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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
                'event' => 'google_login_blocked',
                'email' => $googleUser->getEmail(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Usuario con email existente intento login con Google pero tiene contrasena.',
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'Este correo ya esta registrado con una contrasena. Inicia sesion con tu contrasena.',
            ]);
        }

        if ($existing) {
            $user = $existing;
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? explode('@', $googleUser->getEmail())[0],
                'email' => $googleUser->getEmail(),
                'role' => User::ROLE_CLIENT,
                'google_id' => $googleUser->getId(),
                'google_avatar' => $googleUser->getAvatar(),
                'password' => null,
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
                return redirect()->route('mfa.setup');
            }
            if (in_array('webauthn', $unconfigured)) {
                return redirect()->route('mfa.webauthn.setup');
            }
        }

        if (!empty($pending)) {
            if ($pending[0] === 'totp') {
                return redirect()->route('mfa.verify');
            }
            if ($pending[0] === 'webauthn') {
                return redirect()->route('mfa.webauthn.auth');
            }
        }

        $pendingId = $request->session()->pull('pending_auth_user_id');
        if ($pendingId) {
            Auth::loginUsingId($pendingId);
        }
        $request->session()->forget('pending_auth_user_id');

        return redirect()->intended(route($user->homeRouteName()));
    }
}
