<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['subject', 'user_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['last_read_at', 'left_at']);
    }

    public function activeParticipants(): BelongsToMany
    {
        return $this->participants()->wherePivotNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('users.id', $user->id)->wherePivotNull('left_at')->exists();
    }
}
