<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpFoundation\IpUtils;

class IpBan extends Model
{
    protected $fillable = ['ip', 'reason', 'created_by', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    // Active ban matching the address, single IPs and CIDR ranges alike
    public static function matching(?string $ip): ?self
    {
        if (! $ip) {
            return null;
        }

        return static::active()->get()->first(fn (self $ban) => IpUtils::checkIp($ip, $ban->ip));
    }
}
