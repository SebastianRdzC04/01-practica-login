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
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
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
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
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
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return LoginLockout::throttleKey((string) $this->string('email'), (string) $this->ip());
    }
}
