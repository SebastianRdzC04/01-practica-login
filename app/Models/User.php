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

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->password && $user->google_id) {
                throw new \LogicException('A user cannot have both a password and a Google ID.');
            }
        });
    }

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
     * Returns the WebAuthn credentials from the package-provided trait.
     * The WebAuthnAuthentication trait adds the webAuthnCredentials() morphMany relationship.
     */
    public function hasWebauthnEnabled(): bool
    {
        return $this->webAuthnCredentials()->exists();
    }

}
