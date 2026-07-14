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
     * Muestra la vista de registro de nuevos usuarios.
     *
     * Renderiza la página de registro. Antes de mostrar la vista, registra
     * un evento de auditoría con la IP y el user agent para llevar un control
     * de accesos a la página de registro.
     *
     * @return View Vista con el formulario de registro.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
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
     * Procesa una solicitud de registro entrante.
     *
     * Valida los datos del formulario (nombre, email, contraseña), verifica
     * el token de reCAPTCHA si está configurado, crea el usuario en la base
     * de datos y lo autentica automáticamente. Registra eventos de auditoría
     * en cada etapa y aplica limitación de tasa contra fuerza bruta.
     *
     * @param  Request $request Solicitud HTTP con los datos del registro.
     * @return RedirectResponse Redirección a la ruta de inicio del usuario.
     * @throws ValidationException Si falla la validación o reCAPTCHA.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
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
                    'email' => __('reCAPTCHA verification failed.'),
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
     * @see https://docs.phpdoc.org/ PHPDoc standard
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

    /**
     * Genera la clave única para limitar la tasa de solicitudes de registro.
     *
     * Combina el correo electrónico (en minúsculas) con la dirección IP para
     * crear una clave única que identifica intentos de registro, permitiendo
     * al limitador de tasa rastrear y bloquear solicitudes específicas.
     *
     * @param  Request $request Solicitud HTTP con los datos del registro.
     * @return string Clave única en formato 'register:email|ip'.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    protected function throttleKey(Request $request): string
    {
        return 'register:' . strtolower((string) $request->input('email')) . '|' . $request->ip();
    }
}
