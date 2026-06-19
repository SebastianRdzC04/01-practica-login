<?php

namespace App\Support;

use App\Models\AuthLog as AuthLogModel;

class AuthLog
{
    public const EVENT_LOGIN_SCREEN_VIEWED = 'login_screen_viewed';

    public const EVENT_LOGIN_ATTEMPT = 'login_attempt';

    public const EVENT_LOGIN_SUCCESS = 'login_success';

    public const EVENT_LOGIN_FAILED = 'login_failed';

    public const EVENT_LOGIN_LOCKOUT = 'login_lockout';

    public const EVENT_LOGOUT = 'logout';

    public const EVENT_REGISTER_ATTEMPT = 'register_attempt';

    public const EVENT_REGISTER_SUCCESS = 'register_success';

    public const EVENT_REGISTER_VIEW = 'register_view';

    public const EVENT_REGISTER_FAILED = 'register_failed';

    public const EVENT_GOOGLE_REDIRECT = 'google_redirect';

    public const EVENT_GOOGLE_CALLBACK = 'google_callback';

    public const EVENT_GOOGLE_SUCCESS = 'google_login_success';

    public const EVENT_GOOGLE_BLOCKED = 'google_login_blocked';

    public const EVENT_GOOGLE_ERROR = 'google_login_error';

    public const EVENT_GOOGLE_NEW_USER = 'google_new_user';

    public const EVENT_TOTP_SETUP_VIEW = 'totp_setup_view';

    public const EVENT_TOTP_SETUP_CONFIRM = 'totp_setup_confirm';

    public const EVENT_TOTP_SETUP_FAILED = 'totp_setup_failed';

    public const EVENT_TOTP_VERIFY_VIEW = 'totp_verify_view';

    public const EVENT_TOTP_VERIFY = 'totp_verify';

    public const EVENT_TOTP_VERIFY_FAILED = 'totp_verify_failed';

    public const EVENT_WEBAUTHN_SETUP_VIEW = 'webauthn_setup_view';

    public const EVENT_WEBAUTHN_REGISTER = 'webauthn_register';

    public const EVENT_WEBAUTHN_REGISTER_FAILED = 'webauthn_register_failed';

    public const EVENT_WEBAUTHN_AUTH_VIEW = 'webauthn_auth_view';

    public const EVENT_WEBAUTHN_AUTH = 'webauthn_auth';

    public const EVENT_WEBAUTHN_AUTH_FAILED = 'webauthn_auth_failed';

    public const EVENT_PROFILE_VIEW = 'profile_view';

    public const EVENT_PROFILE_UPDATE = 'profile_update';

    public const EVENT_PROFILE_UPDATE_FAILED = 'profile_update_failed';

    public const EVENT_ACCOUNT_DELETION = 'account_deletion';

    public const EVENT_ACCOUNT_DELETION_FAILED = 'account_deletion_failed';

    public const EVENT_PASSWORD_RESET_REQUEST = 'password_reset_request';

    public const EVENT_PASSWORD_RESET_REQUEST_FAILED = 'password_reset_request_failed';

    public const EVENT_PASSWORD_RESET = 'password_reset';

    public const EVENT_PASSWORD_RESET_FAILED = 'password_reset_failed';

    public const EVENT_PASSWORD_CHANGE = 'password_change';

    public const EVENT_PASSWORD_CHANGE_FAILED = 'password_change_failed';

    public const EVENT_PASSWORD_CONFIRM = 'password_confirm';

    public const EVENT_PASSWORD_CONFIRM_FAILED = 'password_confirm_failed';

    public const EVENT_EMAIL_VERIFICATION_VIEW = 'email_verification_view';

    public const EVENT_EMAIL_VERIFICATION_RESEND = 'email_verification_resend';

    public const EVENT_EMAIL_VERIFIED = 'email_verified';

    public const EVENT_INACTIVITY_LOGOUT = 'inactivity_logout';

    public const EVENT_UNAUTHORIZED_ACCESS = 'unauthorized_access';

    public const EVENT_ROUTE_VISIT = 'route_visit';

    public const EVENT_EXCEPTION = 'exception';

    public const EVENT_MFA_REDIRECT = 'mfa_redirect';

    public const EVENT_LOGIN_FULL = 'login_full_complete';

    public const EVENT_SESSION_HEARTBEAT = 'session_heartbeat';

    public static function info(string $message, array $context = []): void
    {
        static::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        static::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        static::write('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        static::write('debug', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $context = static::normalizeContext($context);

        AuthLogModel::create([
            'level' => $level,
            'message' => $message,
            'event' => $context['event'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'email' => $context['email'] ?? null,
            'role' => $context['role'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'succeeded' => $context['succeeded'] ?? null,
            'context' => array_diff_key($context, array_flip([
                'event', 'user_id', 'email', 'role',
                'ip_address', 'user_agent', 'succeeded',
                'app_env', 'app_server_id',
            ])),
        ]);
    }

    private static function normalizeContext(array $context): array
    {
        return array_merge([
            'app_env' => config('app.env'),
            'app_server_id' => config('app.server_id'),
        ], $context);
    }
}
