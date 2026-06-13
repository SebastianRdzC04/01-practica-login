<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthLog;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Services\RecaptchaService;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        AuthLog::info('Registration page viewed', [
            'event' => AuthLog::EVENT_REGISTER_VIEW,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'message' => 'Pantalla de registro mostrada.',
        ]);

        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        AuthLog::info('Registration attempt received', [
            'event' => AuthLog::EVENT_REGISTER_ATTEMPT,
            'succeeded' => false,
            'email' => (string) $request->input('email'),
            'guard' => 'web',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Solicitud de registro recibida.',
        ]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Verify reCAPTCHA token
        $token = $request->input('g-recaptcha-response');
        if (config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET')) {
            if (! RecaptchaService::verify($token, $request->ip())) {
                RateLimiter::hit($this->throttleKey($request), 60);
                AuthLog::warning('Registration reCAPTCHA failed', [
                    'event' => AuthLog::EVENT_REGISTER_FAILED,
                    'succeeded' => false,
                    'email' => (string) $request->input('email'),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'reCAPTCHA fallo en registro.',
                ]);
                throw ValidationException::withMessages([
                    'email' => 'reCAPTCHA verification failed.',
                ]);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make($request->password),
        ]);

        AuthLog::info('User registered', [
            'event' => AuthLog::EVENT_REGISTER_ATTEMPT,
            'succeeded' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'guard' => 'web',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Usuario registrado exitosamente.',
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route($user->homeRouteName()));
    }

    /**
     * Verifica si la solicitud de registro ha excedido el límite permitido de intentos.
     *
     * Genera una excepción de validación cuando el usuario supera el número máximo
     * de intentos configurado para una combinación de correo electrónico e IP.
     * Además, registra el evento de bloqueo en el sistema de auditoría.
     *
     * @param Request $request Solicitud HTTP que contiene los datos del registro.
     * @return void
     * @throws ValidationException Si se supera el límite de intentos permitidos.
     *
     * @see https://docs.phpdoc.org/ Estándar de documentación PHPDoc.
     */

    protected function ensureIsNotRateLimited(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            event(new Lockout($request));

            $seconds = RateLimiter::availableIn($key);

            AuthLog::warning('Registration rate limited', [
                'event' => AuthLog::EVENT_REGISTER_FAILED,
                'succeeded' => false,
                'email' => (string) $request->input('email'),
                'ip_address' => $request->ip(),
                'message' => 'Demasiados intentos de registro.',
            ]);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }
    }

    protected function throttleKey(Request $request): string
    {
        return 'register:' . strtolower((string) $request->input('email')) . '|' . $request->ip();
    }
}
