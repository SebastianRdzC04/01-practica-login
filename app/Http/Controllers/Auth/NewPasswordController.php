<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuthLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use App\Services\RecaptchaService;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        AuthLog::info('Password reset form viewed', [
            'event' => AuthLog::EVENT_PASSWORD_RESET,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Formulario de restablecimiento de contrasena mostrado.',
        ]);

        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Verify reCAPTCHA
        $token = $request->input('g-recaptcha-response');
        if (config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET')) {
            if (! RecaptchaService::verify($token, $request->ip())) {
                AuthLog::warning('Password reset reCAPTCHA failed', [
                    'event' => AuthLog::EVENT_PASSWORD_RESET_FAILED,
                    'succeeded' => false,
                    'email' => $request->email,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'reCAPTCHA fallo en restablecimiento de contrasena.',
                ]);
                throw ValidationException::withMessages([
                    'email' => 'reCAPTCHA verification failed.',
                ]);
            }
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        $reset = $status == Password::PASSWORD_RESET;

        AuthLog::info('Password ' . ($reset ? 'reset successfully' : 'reset failed'), [
            'event' => $reset ? AuthLog::EVENT_PASSWORD_RESET : AuthLog::EVENT_PASSWORD_RESET_FAILED,
            'succeeded' => $reset,
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => $reset ? 'Contrasena restablecida exitosamente.' : 'Fallo al restablecer la contrasena.',
        ]);

        return $reset
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
