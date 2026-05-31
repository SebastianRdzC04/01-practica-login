<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    use HasFactory;

    public const EVENT_LOGIN_SUCCESS = 'login_success';

    public const EVENT_LOGIN_FAILED = 'login_failed';

    public const EVENT_LOGIN_LOCKOUT = 'login_lockout';

    public const EVENT_LOGOUT = 'logout';

    protected $fillable = [
        'user_id',
        'event',
        'succeeded',
        'email',
        'role',
        'guard',
        'ip_address',
        'user_agent',
        'remember',
        'message',
        'metadata',
    ];

    protected $casts = [
        'succeeded' => 'boolean',
        'remember' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
