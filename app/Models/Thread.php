<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thread extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['section_id', 'user_id', 'title', 'slug', 'is_locked', 'is_pinned', 'posts_count', 'last_post_id', 'last_post_user_id', 'last_post_at', 'prefix', 'solution_post_id'];

    protected $hidden = ['search_vector'];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'is_pinned' => 'boolean',
            'last_post_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Fixed list of thread prefixes; labels are translated in views
    public const PREFIXES = ['question' => 'Question', 'discussion' => 'Discussion', 'guide' => 'Guide', 'bug' => 'Bug', 'idea' => 'Idea', 'event' => 'Event'];

    public function poll(): HasOne
    {
        return $this->hasOne(Poll::class);
    }

    public function solution(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'solution_post_id');
    }

    public function lastPoster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_post_user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function firstPost(): HasOne
    {
        return $this->hasOne(Post::class)->oldestOfMany();
    }

    public function url(?int $page = null): string
    {
        return route('threads.show', ['thread' => $this->id, 'slug' => $this->slug] + ($page ? ['page' => $page] : []));
    }

    // Recompute denormalized counters from visible posts
    public function refreshStats(): void
    {
        $last = $this->posts()->where('is_hidden', false)->latest('id')->first();
        $this->forceFill([
            'posts_count' => $this->posts()->where('is_hidden', false)->count(),
            'last_post_id' => $last?->id,
            'last_post_user_id' => $last?->user_id,
            'last_post_at' => $last?->created_at ?? $this->created_at,
        ])->saveQuietly();
    }
}
