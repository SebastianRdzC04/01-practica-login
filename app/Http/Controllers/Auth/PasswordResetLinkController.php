<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use App\Services\RecaptchaService;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        AuthLog::info('Password reset request page viewed', [
            'event' => AuthLog::EVENT_PASSWORD_RESET_REQUEST,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'message' => 'Pantalla de solicitud de restablecimiento de contrasena mostrada.',
        ]);

        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            if ($user->google_id) {
                AuthLog::warning('Password reset blocked - Google user', [
                    'event' => AuthLog::EVENT_PASSWORD_RESET_REQUEST_FAILED,
                    'succeeded' => false,
                    'email' => $request->email,
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'Usuario de Google intento restablecer contrasena.',
                ]);
                throw ValidationException::withMessages([
                    'email' => __('Los usuarios registrados con Google no pueden restablecer contrasena. Inicia sesion con Google.'),
                ]);
            }

            if ($user->role !== 'cliente') {
                AuthLog::warning('Password reset blocked - non-client role', [
                    'event' => AuthLog::EVENT_PASSWORD_RESET_REQUEST_FAILED,
                    'succeeded' => false,
                    'email' => $request->email,
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => "Usuario con rol {$user->role} intento restablecer contrasena por web.",
                ]);
                throw ValidationException::withMessages([
                    'email' => __('Los usuarios con rol :role no pueden restablecer la contrasena por web. Contacta a un administrador.', ['role' => $user->role]),
                ]);
            }
        }

        // Verify reCAPTCHA
        $token = $request->input('g-recaptcha-response');
        if (config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET')) {
            if (! RecaptchaService::verify($token, $request->ip())) {
                AuthLog::warning('Password reset reCAPTCHA failed', [
                    'event' => AuthLog::EVENT_PASSWORD_RESET_REQUEST_FAILED,
                    'succeeded' => false,
                    'email' => $request->email,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'reCAPTCHA fallo en solicitud de restablecimiento.',
                ]);
                throw ValidationException::withMessages([
                    'email' => 'reCAPTCHA verification failed.',
                ]);
            }
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        $sent = $status == Password::RESET_LINK_SENT;

        AuthLog::info('Password reset link ' . ($sent ? 'sent' : 'failed'), [
            'event' => $sent ? AuthLog::EVENT_PASSWORD_RESET_REQUEST : AuthLog::EVENT_PASSWORD_RESET_REQUEST_FAILED,
            'succeeded' => $sent,
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => $sent ? 'Enlace de restablecimiento de contrasena enviado.' : 'Fallo al enviar enlace de restablecimiento.',
        ]);

        return $sent
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
