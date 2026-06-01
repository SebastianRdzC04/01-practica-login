<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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

        return redirect()->intended(route($request->user()->homeRouteName()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
