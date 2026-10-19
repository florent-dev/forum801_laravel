<?php

namespace App\Models;

use App\Support\Community;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Guild extends Model
{
    public const ROLES = ['owner', 'officer', 'member'];

    public const RECRUITMENT = ['open', 'application', 'closed'];

    protected $fillable = ['name', 'slug', 'tag', 'community', 'emblem', 'description', 'description_html', 'recruitment', 'forum_public'];

    protected $attributes = ['recruitment' => 'application', 'forum_public' => false, 'community' => 'en'];

    protected function casts(): array
    {
        return ['forum_public' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function members(): HasMany
    {
        return $this->hasMany(GuildMember::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(GuildApplication::class);
    }

    public function section(): HasOne
    {
        return $this->hasOne(Section::class);
    }

    public function membershipOf(?User $user): ?GuildMember
    {
        return $user ? $this->members()->where('user_id', $user->id)->first() : null;
    }

    public function roleOf(?User $user): ?string
    {
        return $this->membershipOf($user)?->role;
    }

    public function emblemUrl(): string
    {
        return $this->emblem ? (str_starts_with($this->emblem, 'http') ? $this->emblem : asset($this->emblem)) : asset('img/sections/Tribes.png');
    }

    public function flagUrl(): string
    {
        return Community::flagUrl($this->community);
    }
}
