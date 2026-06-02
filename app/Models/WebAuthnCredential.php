<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebAuthnCredential extends Model
{
    protected $table = 'webauthn_credentials';

    protected $fillable = [
        'user_id',
        'name',
        'credential_id',
        'public_key',
        'sign_count',
        'transports',
    ];

    protected $casts = [
        'transports' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
