<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'avatar_url', 'bio', 'signature', 'status', 'last_seen_at', 'locale', 'hide_online', 'mention_policy'];

    protected $hidden = ['password', 'remember_token', 'registration_ip', 'last_ip'];

    protected $attributes = ['status' => 'active', 'hide_online' => false, 'mention_policy' => 'everyone'];

    public const MENTION_POLICIES = ['everyone', 'friends', 'nobody'];

    protected ?array $friendIdsCache = null;

    // Memoized role assignments for the current request
    protected ?Collection $roleAssignments = null;

    protected ?array $ignoredIdsCache = null;

    protected ?array $blockedIdsCache = null;

    // Memoized active sanctions for the current request
    protected ?Collection $activeSanctions = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'hide_online' => 'boolean',
            'tours_seen' => 'array',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot(['id', 'scope_type', 'scope_id'])->withTimestamps();
    }

    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function roleAssignments(): Collection
    {
        return $this->roleAssignments ??= $this->roles()->get();
    }

    public function flushRoleCache(): void
    {
        $this->roleAssignments = null;
        $this->unsetRelation('roles');
    }

    public function hasRole(string $name, ?Section $section = null): bool
    {
        return $this->roleAssignments()->contains(function (Role $role) use ($name, $section) {
            if ($role->name !== $name) {
                return false;
            }
            if ($role->pivot->scope_type === null) {
                return true;
            }
            return $section !== null
                && $role->pivot->scope_type === 'section'
                && (int) $role->pivot->scope_id === $section->id;
        });
    }

    public function hasGlobalRole(string $name): bool
    {
        return $this->roleAssignments()->contains(fn (Role $r) => $r->name === $name && $r->pivot->scope_type === null);
    }

    public function isAdmin(): bool
    {
        return $this->hasGlobalRole(Role::ADMINISTRATOR);
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class);
    }

    // Staff-only notes written about this user
    public function staffNotes(): HasMany
    {
        return $this->hasMany(UserNote::class);
    }

    public function activeSanctions(): Collection
    {
        return $this->activeSanctions ??= $this->exists ? $this->sanctions()->active()->orderByDesc('id')->get() : collect();
    }

    public function flushSanctionCache(): void
    {
        $this->activeSanctions = null;
    }

    public function activeSanction(string $type): ?Sanction
    {
        return $this->activeSanctions()->firstWhere('type', $type);
    }

    // Suspensions expire on their own when their end date passes
    public function isBanned(): bool
    {
        return $this->activeSanction(Sanction::SUSPENSION) !== null;
    }

    public function isMuted(): bool
    {
        return $this->activeSanction(Sanction::MUTE) !== null;
    }

    // Posting, replying, reacting and messaging need a confirmed, unsanctioned account
    public function canParticipate(): bool
    {
        return $this->hasVerifiedEmail() && ! $this->isBanned() && ! $this->isMuted();
    }

    // Highest role rank, used to stop moderators from sanctioning their peers
    public function staffRank(): int
    {
        return (int) $this->roleAssignments()->max('rank');
    }

    // Users who hide their status never appear online to others
    public function isOnline(): bool
    {
        if ($this->hide_online && auth()->id() !== $this->id) {
            return false;
        }

        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(5));
    }

    public function scopeVisiblyOnline($query)
    {
        return $query->where('last_seen_at', '>', now()->subMinutes(5))
            ->where(fn ($q) => $q->where('hide_online', false)->when(auth()->id(), fn ($q, $id) => $q->orWhere('id', $id)));
    }

    // Ids of accepted friends in both directions, memoized for the request
    public function friendIds(): array
    {
        return $this->friendIdsCache ??= $this->exists
            ? Friendship::whereNotNull('accepted_at')
                ->where(fn ($q) => $q->where('user_id', $this->id)->orWhere('friend_id', $this->id))
                ->get(['user_id', 'friend_id'])
                ->map(fn ($f) => $f->user_id === $this->id ? $f->friend_id : $f->user_id)
                ->unique()->values()->all()
            : [];
    }

    public function isFriendOf(User $other): bool
    {
        return in_array($other->id, $this->friendIds(), true);
    }

    public function flushFriendCache(): void
    {
        $this->friendIdsCache = null;
    }

    // Whether a mention written by the author should notify this user
    public function acceptsMentionFrom(User $author): bool
    {
        return match ($this->mention_policy) {
            'nobody' => false,
            'friends' => $this->isFriendOf($author),
            default => true,
        };
    }

    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Thread::class, 'thread_subscriptions')->withPivot('created_at');
    }

    public function isSubscribedTo(Thread $thread): bool
    {
        return \Illuminate\Support\Facades\DB::table('thread_subscriptions')->where(['user_id' => $this->id, 'thread_id' => $thread->id])->exists();
    }

    public function receivedReactions(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Reaction::class, Post::class);
    }

    public function bookmarks(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'bookmarks')->withPivot('created_at');
    }

    public function ignoredUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_ignores', 'user_id', 'ignored_id')->withPivot('created_at');
    }

    // Ids of ignored users, memoized for the request
    public function ignoredIds(): array
    {
        return $this->ignoredIdsCache ??= $this->exists ? $this->ignoredUsers()->pluck('users.id')->all() : [];
    }

    public function ignores(User $other): bool
    {
        return in_array($other->id, $this->ignoredIds(), true);
    }

    public function guildMemberships(): HasMany
    {
        return $this->hasMany(GuildMember::class)->orderBy('created_at');
    }

    // Guilds of the user, oldest membership first
    public function guilds(): \Illuminate\Support\Collection
    {
        return $this->guildMemberships->pluck('guild')->filter()->values();
    }

    public function guildIds(): array
    {
        return $this->guildMemberships->pluck('guild_id')->all();
    }

    public function guildRole(Guild $guild): ?string
    {
        return $this->guildMemberships->firstWhere('guild_id', $guild->id)?->role;
    }

    public function canJoinMoreGuilds(): bool
    {
        return $this->guildMemberships->count() < (int) \App\Support\ForumSettings::get('max_guilds_per_user');
    }

    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'user_id', 'blocked_id')->withPivot('created_at');
    }

    // Ids of users this user blocked, memoized for the request
    public function blockedIds(): array
    {
        return $this->blockedIdsCache ??= $this->exists ? $this->blockedUsers()->pluck('users.id')->all() : [];
    }

    public function hasBlocked(User $other): bool
    {
        return in_array($other->id, $this->blockedIds(), true);
    }

    public function flushBlockCache(): void
    {
        $this->blockedIdsCache = null;
    }

    // True when either user blocked the other
    public function isBlockedWith(User $other): bool
    {
        return $this->hasBlocked($other) || $other->hasBlocked($this);
    }

    // Users whose posts are collapsed: ignored or blocked
    public function mutedAuthorIds(): array
    {
        return array_values(array_unique([...$this->ignoredIds(), ...$this->blockedIds()]));
    }

    public function hasSeenTour(string $key): bool
    {
        return in_array($key, $this->tours_seen ?? [], true);
    }

    // Rank title earned with visible posts
    public static function rankFor(int $posts): string
    {
        foreach ([1000 => 'Legend', 500 => 'Veteran', 200 => 'Regular', 50 => 'Member', 10 => 'Apprentice'] as $min => $title) {
            if ($posts >= $min) {
                return $title;
            }
        }

        return 'Newcomer';
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)->withPivot(['last_read_at', 'left_at']);
    }

    // Number of conversations with messages the user has not read yet
    public function unreadConversationsCount(): int
    {
        return $this->conversations()
            ->wherePivotNull('left_at')
            ->where(fn ($q) => $q->whereNull('conversation_user.last_read_at')->orWhereColumn('conversations.last_message_at', '>', 'conversation_user.last_read_at'))
            ->count();
    }

    public function assignRole(string $name, ?Section $section = null): void
    {
        $role = Role::where('name', $name)->firstOrFail();
        $this->roles()->attach($role->id, [
            'scope_type' => $section ? 'section' : null,
            'scope_id' => $section?->id,
        ]);
        $this->flushRoleCache();
    }

    public function removeRole(string $name, ?Section $section = null): void
    {
        $role = Role::where('name', $name)->firstOrFail();
        $this->roles()->newPivotStatement()
            ->where('user_id', $this->id)
            ->where('role_id', $role->id)
            ->where('scope_type', $section ? 'section' : null)
            ->where('scope_id', $section?->id)
            ->delete();
        $this->flushRoleCache();
    }

    public function isStaff(): bool
    {
        return $this->roleAssignments()->contains(fn (Role $r) => $r->name !== Role::MEMBER);
    }

    // Color of the highest ranked role, used for the nickname
    public function displayColor(): string
    {
        return $this->roleAssignments()->sortByDesc('rank')->first()?->color ?? '#009D9D';
    }

    public function avatar(): string
    {
        if ($this->avatar_url) {
            return $this->avatar_url;
        }
        // Local generated avatar: initial on a color derived from the name
        $hue = crc32($this->name) % 360;
        $letter = e(mb_strtoupper(mb_substr($this->name, 0, 1)));
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='hsl({$hue},45%,32%)'/><text x='50' y='66' font-family='Verdana,Arial,sans-serif' font-size='46' fill='#c9c9c9' text-anchor='middle'>{$letter}</text></svg>";

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
