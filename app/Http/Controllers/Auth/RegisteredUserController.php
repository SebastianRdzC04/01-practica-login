<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthLog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
}
