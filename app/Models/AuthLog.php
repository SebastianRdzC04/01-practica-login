<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthLog extends Model
{
    protected $table = 'auth_logs';

    protected $fillable = [
        'level',
        'message',
        'event',
        'user_id',
        'email',
        'role',
        'ip_address',
        'user_agent',
        'succeeded',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
        ];
    }
}
