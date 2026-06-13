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
     * Muestra el formulario de edición del perfil del usuario.
     *
     * Renderiza la página de perfil del usuario autenticado para que
     * pueda modificar su nombre y correo electrónico. Registra un
     * evento de auditoría al mostrar la página.
     *
     * @param  Request $request Solicitud HTTP entrante.
     * @return View Vista del formulario de edición de perfil.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
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
     * Actualiza la información del perfil del usuario.
     *
     * Valida y guarda los cambios del perfil (nombre, email). Si el email
     * cambia, marca la verificación como pendiente. Verifica reCAPTCHA
     * antes de procesar. Registra eventos de auditoría detallados.
     *
     * @param  ProfileUpdateRequest $request Solicitud con los datos validados.
     * @return RedirectResponse Redirección a la edición de perfil.
     * @throws ValidationException Si falla la verificación de reCAPTCHA.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
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
     * Elimina la cuenta del usuario autenticado.
     *
     * Requiere confirmación de la contraseña actual y verificación de
     * reCAPTCHA. Invalida la sesión, elimina el usuario de la base de
     * datos y redirige a la página principal. Registra eventos de
     * auditoría en cada etapa del proceso.
     *
     * @param  Request $request Solicitud HTTP con la contraseña de confirmación.
     * @return RedirectResponse Redirección a la página principal.
     * @throws ValidationException Si falla validación de contraseña o reCAPTCHA.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
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
