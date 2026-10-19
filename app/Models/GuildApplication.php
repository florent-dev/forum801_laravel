<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuildApplication extends Model
{
    protected $fillable = ['guild_id', 'user_id', 'message', 'status', 'handled_by', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
