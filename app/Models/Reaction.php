<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    // Allowed reaction types and their display glyphs
    public const TYPES = ['like' => '👍', 'cheese' => '🧀', 'laugh' => '😄', 'heart' => '❤️', 'wow' => '😮'];

    protected $fillable = ['post_id', 'user_id', 'type'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
