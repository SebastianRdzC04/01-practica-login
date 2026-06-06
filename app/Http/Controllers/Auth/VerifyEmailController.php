<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuthLog;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route($user->homeRouteName(), ['verified' => 1]));
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));

            AuthLog::info('Email verified', [
                'event' => AuthLog::EVENT_EMAIL_VERIFIED,
                'succeeded' => true,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => 'Email verificado exitosamente.',
            ]);
        }

        return redirect()->intended(route($user->homeRouteName(), ['verified' => 1]));
    }
}
