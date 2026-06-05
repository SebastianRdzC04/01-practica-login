<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;

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

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function homeRouteName(): string
    {
        return $this->hasRole(self::ROLE_CLIENT) ? 'client.home' : 'dashboard';
    }

    public function requiredFactors(): array
    {
        $map = [
            'cliente' => ['password'],       // solo contraseña
            'usuario' => ['password','totp'],// password + totp
            'administrador' => ['password','totp','webauthn'], // ejemplo con 3
            'logger' => ['password','totp'],
        ];

        return $map[$this->role] ?? ['password'];
    }

    /**
     * Returns the WebAuthn credentials from the package-provided trait.
     * The WebAuthnAuthentication trait adds the webAuthnCredentials() morphMany relationship.
     */
    public function hasWebauthnEnabled(): bool
    {
        return $this->webAuthnCredentials()->exists();
    }

}
