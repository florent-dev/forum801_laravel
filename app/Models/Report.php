<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = ['post_id', 'reporter_id', 'reason', 'status', 'handled_by', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
