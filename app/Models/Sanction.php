<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sanction extends Model
{
    public const WARNING = 'warning';
    public const MUTE = 'mute';
    public const SUSPENSION = 'suspension';

    public const TYPES = [self::WARNING, self::MUTE, self::SUSPENSION];

    protected $fillable = ['user_id', 'moderator_id', 'type', 'reason', 'expires_at', 'revoked_at', 'revoked_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    // Not revoked and not expired; warnings are never "active"
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('type', '!=', self::WARNING)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->type !== self::WARNING && $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
