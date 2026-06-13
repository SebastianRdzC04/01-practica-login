<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\AuthLog;
use App\Support\LoginLockout;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use GuzzleHttp\Client;

class LoginRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     *
     * Todas las solicitudes de inicio de sesión están permitidas sin
     * restricción previa.
     *
     * @return bool  Siempre retorna true.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación para la solicitud de inicio de sesión.
     *
     * Valida que el correo electrónico tenga un formato válido y que
     * la contraseña sea una cadena de texto.
     *
     * @return array<string, Rule|array|string>  Reglas de validación por campo.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Intenta autenticar las credenciales de la solicitud.
     *
     * Verifica el rate limiting, valida el token reCAPTCHA si está
     * configurado, busca al usuario por correo electrónico y comprueba
     * la contraseña. En caso de fallo, registra el evento y lanza
     * una excepción de validación.
     *
     * @throws \Illuminate\Validation\ValidationException  Cuando las credenciales son inválidas o el rate limiter está activo.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        AuthLog::info('Authentication attempt received', [
            'event' => AuthLog::EVENT_LOGIN_ATTEMPT,
            'email' => (string) $this->string('email'),
            'guard' => 'web',
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'remember' => $this->boolean('remember'),
            'message' => 'Intento de inicio de sesion recibido.',
        ]);

        // Verify reCAPTCHA token
        $recaptchaSecret = config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET');
        $token = $this->input('g-recaptcha-response');
        if ($recaptchaSecret) {
            if (! $token) {
                RateLimiter::hit($this->throttleKey());

                throw ValidationException::withMessages([
                    'email' => 'reCAPTCHA token missing. Por favor completa el captcha.',
                ]);
            }

            try {
                $client = new Client(['timeout' => 5]);
                $res = $client->post('https://www.google.com/recaptcha/api/siteverify', [
                    'form_params' => [
                        'secret' => $recaptchaSecret,
                        'response' => $token,
                        'remoteip' => $this->ip(),
                    ],
                ]);

                $body = json_decode((string) $res->getBody(), true);
                if (! ($body['success'] ?? false)) {
                    RateLimiter::hit($this->throttleKey());
                    throw ValidationException::withMessages([
                        'email' => 'reCAPTCHA verification failed.',
                    ]);
                }
            } catch (\Exception $e) {
                RateLimiter::hit($this->throttleKey());
                throw ValidationException::withMessages([
                    'email' => 'No se pudo verificar reCAPTCHA. Inténtalo de nuevo.',
                ]);
            }
        }

        $user = User::where('email', $this->string('email'))->first();

        if (! $user || ! Hash::check($this->string('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            AuthLog::warning('Authentication failed', [
                'event' => AuthLog::EVENT_LOGIN_FAILED,
                'succeeded' => false,
                'email' => (string) $this->string('email'),
                'user_id' => $user?->id,
                'role' => $user?->role,
                'guard' => 'web',
                'ip_address' => $this->ip(),
                'user_agent' => $this->userAgent(),
                'message' => 'Credenciales invalidas.',
            ]);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $this->session()->put('pending_auth_remember', $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Verifica que la solicitud no supere el límite de intentos.
     *
     * Si el rate limiter indica que se han excedido los intentos
     * permitidos, dispara un evento Lockout y lanza una excepción
     * de validación con el tiempo restante de bloqueo.
     *
     * @throws \Illuminate\Validation\ValidationException  Cuando se ha superado el límite de intentos.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), LoginLockout::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Obtiene la clave de estrangulamiento para el rate limiter.
     *
     * Delega en LoginLockout::throttleKey usando el correo y la
     * dirección IP de la solicitud actual.
     *
     * @return string  Clave única de estrangulamiento.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function throttleKey(): string
    {
        return LoginLockout::throttleKey((string) $this->string('email'), (string) $this->ip());
    }
}
