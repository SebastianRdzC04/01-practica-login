<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthLog;
use App\Support\LoginLockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        $email = (string) request()->old('email', request()->query('email', ''));

        AuthLog::info('Login screen viewed', [
            'event' => AuthLog::EVENT_LOGIN_SCREEN_VIEWED,
            'succeeded' => true,
            'email' => $email !== '' ? $email : null,
            'guard' => 'web',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'message' => 'Pantalla de inicio de sesion mostrada.',
        ]);

        return view('auth.login', [
            'loginLockout' => LoginLockout::state($email, (string) request()->ip()),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $email = $request->string('email');
        $user = User::where('email', $email)->firstOrFail();

        $request->session()->put('pending_auth_user_id', $user->id);

        $required = $user->requiredFactors();
        $pending = array_values(array_filter($required, fn($f) => $f !== 'password'));

        $unconfigured = [];
        foreach ($pending as $factor) {
            if ($factor === 'totp' && ! $user->two_factor_enabled) {
                $unconfigured[] = 'totp';
            }
            if ($factor === 'webauthn' && ! $user->webAuthnCredentials()->exists()) {
                $unconfigured[] = 'webauthn';
            }
        }

        session(['factors_required' => $pending, 'factors_passed' => []]);

        if (! empty($unconfigured)) {
            if (in_array('totp', $unconfigured)) {
                return redirect()->route('mfa.setup');
            }
            if (in_array('webauthn', $unconfigured)) {
                return redirect()->route('mfa.webauthn.setup');
            }
        }

        if (! empty($pending)) {
            if ($pending[0] === 'totp') {
                return redirect()->route('mfa.verify');
            }
            if ($pending[0] === 'webauthn') {
                return redirect()->route('mfa.webauthn.auth');
            }
        }

        Auth::loginUsingId($user->id, $request->session()->pull('pending_auth_remember', false));
        $request->session()->forget('pending_auth_user_id');

        return redirect()->intended(route($request->user()->homeRouteName()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'two_factor_passed', 'factors_required', 'factors_passed',
            'mfa_secret', 'pending_auth_user_id', 'pending_auth_remember',
            'webauthn_challenge',
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
