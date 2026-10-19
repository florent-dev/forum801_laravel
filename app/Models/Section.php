<?php

namespace App\Models;

use App\Support\Community;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Section extends Model
{
    use HasFactory;

    // Mirror the database defaults so new instances are complete before reload
    protected $attributes = ['community' => 'en', 'visibility' => 'public', 'position' => 0];

    protected $fillable = ['name', 'slug', 'community', 'description', 'icon', 'visibility', 'position', 'guild_id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    public function latestThread(): HasOne
    {
        return $this->hasOne(Thread::class)->ofMany('last_post_at', 'max');
    }

    public function flagUrl(): string
    {
        return Community::flagUrl($this->community ?? config('forum.default_community'));
    }

    public function communityLabel(): string
    {
        return Community::label($this->community ?? config('forum.default_community'));
    }

    public function scopeInCommunity(Builder $query, string $code): Builder
    {
        return $query->where('community', $code);
    }

    public function guild(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    public function iconUrl(): string
    {
        return $this->icon ? (str_starts_with($this->icon, 'http') ? $this->icon : asset($this->icon)) : asset('img/sections/Discussions.png');
    }

    // Restrict a query to the sections the given user may see
    // Regular forum sections, without guild forums
    public function scopeForum(Builder $query): Builder
    {
        return $query->whereNull('guild_id');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->isAdmin() || $user?->hasGlobalRole(Role::PRIVATE_MODERATOR)) {
            return $query;
        }
        $scopedIds = $user
            ? $user->roleAssignments()->where('pivot.scope_type', 'section')->pluck('pivot.scope_id')->all()
            : [];

        $guildIds = $user ? $user->guildIds() : [];

        return $query->where(fn ($q) => $q->where('visibility', 'public')
            ->orWhereIn('id', $scopedIds)
            // Guild forums: members always, everyone when the guild opened its forum
            ->orWhereIn('guild_id', Guild::where('forum_public', true)->select('id'))
            ->when($guildIds, fn ($q) => $q->orWhereIn('guild_id', $guildIds)));
    }
}
