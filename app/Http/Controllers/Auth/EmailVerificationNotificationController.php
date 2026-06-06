<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuthLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route($user->homeRouteName()));
        }

        $user->sendEmailVerificationNotification();

        AuthLog::info('Email verification link resent', [
            'event' => AuthLog::EVENT_EMAIL_VERIFICATION_RESEND,
            'succeeded' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Enlace de verificacion de email reenviado.',
        ]);

        return back()->with('status', 'verification-link-sent');
    }
}
