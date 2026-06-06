<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\AuthLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use App\Services\RecaptchaService;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        AuthLog::info('Profile page viewed', [
            'event' => AuthLog::EVENT_PROFILE_VIEW,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'role' => $user?->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Pagina de perfil mostrada.',
        ]);

        return view('profile.edit', [
            'user' => $user,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Verify reCAPTCHA token
        $token = $request->input('g-recaptcha-response');
        if (config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET')) {
            if (! RecaptchaService::verify($token, $request->ip())) {
                AuthLog::warning('Profile update reCAPTCHA failed', [
                    'event' => AuthLog::EVENT_PROFILE_UPDATE_FAILED,
                    'succeeded' => false,
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'role' => $user?->role,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'reCAPTCHA fallo en actualizacion de perfil.',
                ]);
                throw ValidationException::withMessages([
                    'name' => 'reCAPTCHA verification failed.',
                ]);
            }
        }

        $oldEmail = $user->email;
        $user->fill($request->validated());
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        AuthLog::info('Profile updated', [
            'event' => AuthLog::EVENT_PROFILE_UPDATE,
            'succeeded' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'old_email' => $emailChanged ? $oldEmail : null,
            'email_changed' => $emailChanged,
            'role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => $emailChanged ? 'Perfil actualizado con cambio de email.' : 'Perfil actualizado.',
        ]);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        // Verify reCAPTCHA token for account deletion
        $token = $request->input('g-recaptcha-response');
        if (config('services.recaptcha.secret') ?? env('RECAPTCHA_SECRET')) {
            if (! RecaptchaService::verify($token, $request->ip())) {
                AuthLog::warning('Account deletion reCAPTCHA failed', [
                    'event' => AuthLog::EVENT_ACCOUNT_DELETION_FAILED,
                    'succeeded' => false,
                    'user_id' => $user?->id,
                    'email' => $user?->email,
                    'role' => $user?->role,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'message' => 'reCAPTCHA fallo en eliminacion de cuenta.',
                ]);
                throw ValidationException::withMessages([
                    'password' => 'reCAPTCHA verification failed.',
                ]);
            }
        }

        AuthLog::warning('Account deleted', [
            'event' => AuthLog::EVENT_ACCOUNT_DELETION,
            'succeeded' => true,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'role' => $user?->role,
            'name' => $user?->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Cuenta de usuario eliminada.',
        ]);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
