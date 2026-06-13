<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Manejo de bloqueo por límite de intentos de inicio de sesión.
 *
 * Proporciona métodos estáticos para gestionar el rate limiting
 * durante la autenticación, incluyendo la generación de claves
 * de estrangulamiento (throttle key), verificación de bloqueo,
 * tiempo restante y estado completo del bloqueo.
 *
 * @see https://docs.phpdoc.org/ PHPDoc standard
 */
class LoginLockout
{
    public const MAX_ATTEMPTS = 5;

    /**
     * Genera la clave de estrangulamiento para el rate limiter.
     *
     * Combina el correo electrónico y la dirección IP en formato
     * normalizado (minúsculas y transliterado) para identificar
     * de forma única los intentos de un mismo origen.
     *
     * @param  string  $email      Correo electrónico del usuario.
     * @param  string  $ipAddress  Dirección IP del solicitante.
     * @return string  Clave única de estrangulamiento.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public static function throttleKey(string $email, string $ipAddress): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ipAddress);
    }

    /**
     * Verifica si se ha superado el límite de intentos permitidos.
     *
     * Consulta al rate limiter de Laravel si la clave de
     * estrangulamiento ha excedido el máximo de intentos definido
     * (MAX_ATTEMPTS).
     *
     * @param  string  $email      Correo electrónico del usuario.
     * @param  string  $ipAddress  Dirección IP del solicitante.
     * @return bool  True si el origen está bloqueado por exceso de intentos.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public static function isLocked(string $email, string $ipAddress): bool
    {
        return RateLimiter::tooManyAttempts(static::throttleKey($email, $ipAddress), static::MAX_ATTEMPTS);
    }

    /**
     * Obtiene los segundos restantes hasta que se libere el bloqueo.
     *
     * @param  string  $email      Correo electrónico del usuario.
     * @param  string  $ipAddress  Dirección IP del solicitante.
     * @return int  Número de segundos restantes de bloqueo.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public static function secondsRemaining(string $email, string $ipAddress): int
    {
        return RateLimiter::availableIn(static::throttleKey($email, $ipAddress));
    }

    /**
     * Obtiene el estado completo del bloqueo de autenticación.
     *
     * Retorna un arreglo asociativo con la información de si el
     * origen está bloqueado, los segundos restantes y el máximo
     * de intentos permitidos.
     *
     * @param  string  $email      Correo electrónico del usuario.
     * @param  string  $ipAddress  Dirección IP del solicitante.
     * @return array{locked: bool, seconds_remaining: int, max_attempts: int}  Estado del bloqueo.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public static function state(string $email, string $ipAddress): array
    {
        $secondsRemaining = static::secondsRemaining($email, $ipAddress);

        return [
            'locked' => static::isLocked($email, $ipAddress),
            'seconds_remaining' => $secondsRemaining,
            'max_attempts' => static::MAX_ATTEMPTS,
        ];
    }
}
