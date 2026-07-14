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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use GuzzleHttp\Client;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

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

        $recaptchaSecret = config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET');
        $token = $this->input('g-recaptcha-response');
        if ($recaptchaSecret) {
            Log::info('reCAPTCHA verify started', [
                'has_token' => (bool) $token,
                'token_length' => $token ? strlen($token) : 0,
                'ip' => $this->ip(),
            ]);

            if (! $token) {
                RateLimiter::hit($this->throttleKey());
                Log::warning('reCAPTCHA token missing');

                throw ValidationException::withMessages([
                    'email' => __('reCAPTCHA token missing. Por favor completa el captcha.'),
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
                Log::info('reCAPTCHA siteverify response', [
                    'success' => $body['success'] ?? false,
                    'error_codes' => $body['error-codes'] ?? [],
                    'hostname' => $body['hostname'] ?? null,
                    'action' => $body['action'] ?? null,
                    'score' => $body['score'] ?? null,
                ]);

                if (! ($body['success'] ?? false)) {
                    RateLimiter::hit($this->throttleKey());
                    throw ValidationException::withMessages([
                        'email' => __('reCAPTCHA verification failed.'),
                    ]);
                }
            } catch (ValidationException $ve) {
                throw $ve;
            } catch (\Exception $e) {
                RateLimiter::hit($this->throttleKey());
                Log::error('reCAPTCHA Guzzle exception', [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);
                throw ValidationException::withMessages([
                    'email' => __('No se pudo verificar reCAPTCHA. Inténtalo de nuevo.'),
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

    public function throttleKey(): string
    {
        return LoginLockout::throttleKey((string) $this->string('email'), (string) $this->ip());
    }
}
