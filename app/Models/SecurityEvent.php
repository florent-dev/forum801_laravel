<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    // Event types and their default severity
    public const TYPES = [
        'login' => 'info',
        'logout' => 'info',
        'login_failed' => 'warning',
        'lockout' => 'critical',
        'register' => 'info',
        'email_verified' => 'info',
        'password_changed' => 'warning',
        'email_changed' => 'warning',
        'banned_ip_blocked' => 'warning',
        'ban_evasion_suspected' => 'critical',
        'ip_ban_added' => 'warning',
        'ip_ban_removed' => 'info',
        'maintenance_on' => 'warning',
        'maintenance_off' => 'warning',
        'maintenance_scheduled' => 'info',
        'maintenance_access' => 'warning',
        'maintenance_secret_regenerated' => 'warning',
        'admin_granted' => 'warning',
        'admin_revoked' => 'warning',
    ];

    protected $fillable = ['user_id', 'type', 'severity', 'ip', 'user_agent', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
