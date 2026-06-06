<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuthLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Services\RecaptchaService;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password view.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Verify reCAPTCHA token
        $token = $request->input('g-recaptcha-response');
        if (config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET')) {
            if (! RecaptchaService::verify($token, $request->ip())) {
                AuthLog::warning('Password confirm reCAPTCHA failed', [
                    'event' => AuthLog::EVENT_PASSWORD_CONFIRM_FAILED,
                    'succeeded' => false,
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'role' => $user?->role,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'reCAPTCHA fallo en confirmacion de contrasena.',
                ]);
                throw ValidationException::withMessages([
                    'password' => 'reCAPTCHA verification failed.',
                ]);
            }
        }
        if (! Auth::guard('web')->validate([
            'email' => $user->email,
            'password' => $request->password,
        ])) {
            AuthLog::warning('Password confirmation failed', [
                'event' => AuthLog::EVENT_PASSWORD_CONFIRM_FAILED,
                'succeeded' => false,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Confirmacion de contrasena fallida.',
            ]);
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        AuthLog::info('Password confirmed', [
            'event' => AuthLog::EVENT_PASSWORD_CONFIRM,
            'succeeded' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Contrasena confirmada exitosamente.',
        ]);

        return redirect()->intended(route($user->homeRouteName()));
    }
}
