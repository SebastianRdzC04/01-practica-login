<?php

namespace App\Support;

use App\Models\User;

/**
 * Protección por inactividad del usuario autenticado.
 *
 * Define los tiempos de espera y la configuración para detectar
 * y notificar la inactividad del usuario en sesiones con roles
 * de alto privilegio (administrador, logger). Utiliza claves de
 * sesión para almacenar el estado de la protección.
 *
 * @see https://docs.phpdoc.org/ PHPDoc standard
 */
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
     * Obtiene la configuración de inactividad para un usuario dado.
     *
     * La protección solo se habilita para roles de administrador y
     * logger. Retorna los tiempos de espera por defecto para el
     * modal, la advertencia y el cierre de sesión del servidor.
     *
     * @param  \App\Models\User|null  $user  Usuario autenticado o null si es invitado.
     * @return array{enabled: bool, modal_timeout_seconds: int, warning_timeout_seconds: int, server_timeout_seconds: int}  Configuración de inactividad.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
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
