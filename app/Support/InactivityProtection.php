<?php

namespace App\Support;

use App\Models\User;

class InactivityProtection
{
    public const SESSION_KEY_LAST_ACTIVITY_AT = 'inactivity.last_activity_at';

    public const SESSION_KEY_PROTECTED = 'inactivity.protected';

    public const SESSION_KEY_MODAL_TIMEOUT_SECONDS = 'inactivity.modal_timeout_seconds';

    public const SESSION_KEY_WARNING_TIMEOUT_SECONDS = 'inactivity.warning_timeout_seconds';

    public const SESSION_KEY_SERVER_TIMEOUT_SECONDS = 'inactivity.server_timeout_seconds';

    public const DEFAULT_MODAL_TIMEOUT_SECONDS = 30;

    public const DEFAULT_WARNING_TIMEOUT_SECONDS = 10;

    public const DEFAULT_SERVER_TIMEOUT_SECONDS = 300;

    /**
     * @return array{enabled: bool, modal_timeout_seconds: int, warning_timeout_seconds: int, server_timeout_seconds: int}
     */
    public static function configFor(?User $user): array
    {
        $enabled = $user?->hasRole(User::ROLE_ADMIN, User::ROLE_LOGGER) ?? false;

        return [
            'enabled' => $enabled,
            'modal_timeout_seconds' => self::DEFAULT_MODAL_TIMEOUT_SECONDS,
            'warning_timeout_seconds' => self::DEFAULT_WARNING_TIMEOUT_SECONDS,
            'server_timeout_seconds' => self::DEFAULT_SERVER_TIMEOUT_SECONDS,
        ];
    }
}
