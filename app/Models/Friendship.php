<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    protected $fillable = ['user_id', 'friend_id', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }
}
