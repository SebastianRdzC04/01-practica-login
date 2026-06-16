<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Log de auditoría para eventos de autenticación.
 *
 * Proporciona una fachada simplificada sobre el canal 'auth' de
 * Laravel Log, agregando automáticamente el entorno de aplicación
 * (APP_ENV) al contexto de cada mensaje. Centraliza todas las
 * constantes de eventos relacionados con autenticación, registro,
 * TOTP, WebAuthn, Google, perfil, contraseñas y actividad.
 *
 * @see https://docs.phpdoc.org/ PHPDoc standard
 */
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

    /**
     * Registra un mensaje informativo en el canal de autenticación.
     *
     * @param  string  $message  Mensaje descriptivo del evento.
     * @param  array<string, mixed>  $context  Contexto adicional del evento.
     * @return void
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public static function info(string $message, array $context = []): void
    {
        Log::channel('auth')->info($message, static::normalizeContext($context));
    }

    /**
     * Registra un mensaje de advertencia en el canal de autenticación.
     *
     * @param  string  $message  Mensaje descriptivo de la advertencia.
     * @param  array<string, mixed>  $context  Contexto adicional del evento.
     * @return void
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public static function warning(string $message, array $context = []): void
    {
        Log::channel('auth')->warning($message, static::normalizeContext($context));
    }

    /**
     * Registra un mensaje de error en el canal de autenticación.
     *
     * @param  string  $message  Mensaje descriptivo del error.
     * @param  array<string, mixed>  $context  Contexto adicional del evento.
     * @return void
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public static function error(string $message, array $context = []): void
    {
        Log::channel('auth')->error($message, static::normalizeContext($context));
    }

    /**
     * Registra un mensaje de depuración en el canal de autenticación.
     *
     * @param  string  $message  Mensaje descriptivo para depuración.
     * @param  array<string, mixed>  $context  Contexto adicional del evento.
     * @return void
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public static function debug(string $message, array $context = []): void
    {
        Log::channel('auth')->debug($message, static::normalizeContext($context));
    }

    /**
     * Normaliza el contexto agregando el entorno de aplicación.
     *
     * Fusiona el arreglo de contexto recibido con el valor de la
     * configuración `app.env`, asegurando que cada registro incluya
     * el entorno actual (local, production, etc.).
     *
     * @param  array<string, mixed>  $context  Contexto original del evento.
     * @return array<string, mixed>  Contexto enriquecido con app_env.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    private static function normalizeContext(array $context): array
    {
        return array_merge([
            'app_env' => config('app.env'),
            'app_server_id' => config('app.server_id'),
        ], $context);
    }
}
