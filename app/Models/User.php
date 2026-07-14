<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;

/**
 * Modelo de Usuario del sistema de autenticación multifactor.
 *
 * Representa un usuario autenticable con soporte para roles
 * (cliente, usuario, administrador, logger), autenticación
 * mediante contraseña, Google ID, TOTP y WebAuthn. Integra
 * los traits de Laravel Sanctum, WebAuthn, Notificaciones y Factory.
 *
 * @property int|null $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string $role
 * @property string|null $google_id
 * @property string|null $google_avatar
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\Laragear\WebAuthn\Models\WebAuthnCredential[] $webAuthnCredentials
 *
 * @see https://docs.phpdoc.org/ PHPDoc standard
 */
class User extends Authenticatable implements WebAuthnAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable, WebAuthnAuthentication;

    public const ROLE_CLIENT = 'cliente';

    public const ROLE_USER = 'usuario';

    public const ROLE_ADMIN = 'administrador';

    public const ROLE_LOGGER = 'logger';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'google_id',
        'google_avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Inicializa eventos del modelo Eloquent.
     *
     * Registra un evento `saving` que impide que un usuario tenga
     * simultáneamente una contraseña y un Google ID, lanzando una
     * excepción LogicException en caso de conflicto.
     *
     * @return void
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->password && $user->google_id) {
                throw new \LogicException(__('A user cannot have both a password and a Google ID.'));
            }
        });
    }

    /**
     * Determina si el usuario posee alguno de los roles especificados.
     *
     * Compara el rol del usuario contra la lista de roles recibida
     * usando una comparación estricta (in_array con parámetro strict).
     *
     * @param  string  ...$roles  Lista de roles a verificar.
     * @return bool  True si el rol del usuario coincide con alguno de los roles dados.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Obtiene el nombre de la ruta de inicio según el rol del usuario.
     *
     * Los usuarios con rol 'cliente' son redirigidos a 'client.home';
     * el resto de roles reciben la ruta 'dashboard'.
     *
     * @return string  Nombre de la ruta de inicio.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function homeRouteName(): string
    {
        return $this->hasRole(self::ROLE_CLIENT) ? 'client.home' : 'dashboard';
    }

    /**
     * Devuelve los factores de autenticación requeridos según el rol.
     *
     * Mapea cada rol a una lista ordenada de factores (password, totp,
     * webauthn). Si el usuario tiene vinculado un Google ID, se excluye
     * el factor 'password' de la lista resultante.
     *
     * @return array<int, string>  Arreglo con los factores de autenticación requeridos.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function requiredFactors(): array
    {
        $map = [
            'cliente' => ['password'],
            'usuario' => ['password','totp'],
            'administrador' => ['password','totp','webauthn'],
            'logger' => ['password','totp'],
        ];

        $factors = $map[$this->role] ?? ['password'];

        if ($this->google_id) {
            $factors = array_values(array_filter($factors, fn($f) => $f !== 'password'));
        }

        return $factors;
    }

    /**
     * Verifica si el usuario tiene credenciales WebAuthn registradas.
     *
     * Consulta la relación morphMany provista por el trait
     * WebAuthnAuthentication para determinar si existe al menos una
     * credencial WebAuthn asociada al usuario.
     *
     * @return bool  True si existe al menos una credencial WebAuthn.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function hasWebauthnEnabled(): bool
    {
        return $this->webAuthnCredentials()->exists();
    }

}
