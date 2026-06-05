<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\LoginLockoutStatusController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SessionActivityController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::get('login/lockout-status', LoginLockoutStatusController::class)
        ->name('login.lockout-status');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('auth/google', [GoogleLoginController::class, 'redirect'])
        ->name('auth.google');

    Route::get('auth/google/callback', [GoogleLoginController::class, 'callback'])
        ->name('auth.google.callback');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('session/activity', SessionActivityController::class)
        ->middleware('inactivity.protected')
        ->name('session.activity');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::middleware('pending.auth')->group(function () {
    Route::get('mfa/setup', [\App\Http\Controllers\TwoFactorController::class, 'showSetup'])->name('mfa.setup');
    Route::post('mfa/confirm', [\App\Http\Controllers\TwoFactorController::class, 'confirmSetup'])->name('mfa.confirm');
    Route::get('mfa/verify', [\App\Http\Controllers\TwoFactorController::class, 'showVerify'])->name('mfa.verify');
    Route::post('mfa/verify', [\App\Http\Controllers\TwoFactorController::class, 'verify'])->name('mfa.verify.post');

    Route::get('mfa/webauthn/setup', [\App\Http\Controllers\WebAuthnController::class, 'showSetup'])->name('mfa.webauthn.setup');
    Route::get('mfa/webauthn/options', [\App\Http\Controllers\WebAuthnController::class, 'options'])->name('mfa.webauthn.options');
    Route::post('mfa/webauthn/register', [\App\Http\Controllers\WebAuthnController::class, 'register'])->name('mfa.webauthn.register');
    Route::get('mfa/webauthn/auth', [\App\Http\Controllers\WebAuthnController::class, 'showAuthenticate'])->name('mfa.webauthn.auth');
    Route::get('mfa/webauthn/assertion-options', [\App\Http\Controllers\WebAuthnController::class, 'assertionOptions'])->name('mfa.webauthn.assertion-options');
    Route::post('mfa/webauthn/authenticate', [\App\Http\Controllers\WebAuthnController::class, 'authenticate'])->name('mfa.webauthn.authenticate');
});
