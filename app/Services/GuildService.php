<?php

namespace App\Services;

use App\Models\Guild;
use App\Models\GuildApplication;
use App\Models\GuildMember;
use App\Models\Section;
use App\Models\User;
use App\Notifications\ForumActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuildService
{
    public function __construct(protected MarkupRenderer $renderer)
    {
    }

    public function create(User $founder, array $data): Guild
    {
        Gate::forUser($founder)->authorize('create', Guild::class);

        return DB::transaction(function () use ($founder, $data) {
            $guild = Guild::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'tag' => strtoupper($data['tag']),
                'community' => $data['community'],
                'recruitment' => $data['recruitment'] ?? 'application',
                'description' => $data['description'] ?? null,
                'description_html' => isset($data['description']) ? $this->renderer->render($data['description']) : null,
            ]);
            $guild->members()->create(['user_id' => $founder->id, 'role' => 'owner']);
            // Every guild gets its own forum section
            Section::create([
                'name' => $guild->name,
                'slug' => 'guild-'.$guild->slug,
                'community' => $guild->community,
                'description' => __('Forum of the :name guild.', ['name' => $guild->name]),
                'icon' => 'img/sections/tribeforum.png',
                'visibility' => 'private',
                'position' => 1000,
                'guild_id' => $guild->id,
            ]);
            $founder->unsetRelation('guildMemberships');

            return $guild;
        });
    }

    public function update(User $user, Guild $guild, array $data): void
    {
        Gate::forUser($user)->authorize('update', $guild);
        $guild->update([
            'tag' => strtoupper($data['tag']),
            'emblem' => $data['emblem'] ?: null,
            'recruitment' => $data['recruitment'],
            'forum_public' => (bool) $data['forum_public'],
            'description' => $data['description'] ?: null,
            'description_html' => $data['description'] ? $this->renderer->render($data['description']) : null,
        ]);
    }

    // Open guilds take the user at once, others receive an application
    public function join(User $user, Guild $guild, ?string $message = null): ?GuildApplication
    {
        Gate::forUser($user)->authorize('join', $guild);
        if ($guild->recruitment === 'open') {
            $this->addMember($guild, $user);

            return null;
        }
        if ($guild->applications()->where(['user_id' => $user->id, 'status' => 'pending'])->exists()) {
            throw ValidationException::withMessages(['application' => __('Your application is already waiting for an answer.')]);
        }
        $application = $guild->applications()->create(['user_id' => $user->id, 'message' => $message]);
        foreach ($this->leaders($guild) as $leader) {
            $leader->notify(new ForumActivity('guild', ':actor applied to join :guild', ['actor' => $user->name, 'guild' => $guild->name], route('guilds.manage', [$guild, 'tab' => 'applications']), $user));
        }

        return $application;
    }

    public function decide(User $handler, GuildApplication $application, bool $accept): void
    {
        Gate::forUser($handler)->authorize('manageMembers', $application->guild);
        abort_unless($application->status === 'pending', 422);
        DB::transaction(function () use ($handler, $application, $accept) {
            $application->update(['status' => $accept ? 'accepted' : 'rejected', 'handled_by' => $handler->id, 'handled_at' => now()]);
            if ($accept) {
                if ($application->user->guildRole($application->guild)) {
                    throw ValidationException::withMessages(['application' => __('This user is already a member.')]);
                }
                if (! $application->user->canJoinMoreGuilds()) {
                    throw ValidationException::withMessages(['application' => __('This user already belongs to the maximum number of guilds.')]);
                }
                $this->addMember($application->guild, $application->user);
            }
        });
        $application->user->notify(new ForumActivity('guild',
            $accept ? 'Welcome! Your application to :guild was accepted.' : 'Your application to :guild was declined.',
            ['guild' => $application->guild->name], route('guilds.show', $application->guild), $handler));
    }

    public function setRole(User $actor, Guild $guild, GuildMember $member, string $role): void
    {
        abort_unless($member->guild_id === $guild->id && in_array($role, ['officer', 'member'], true), 422);
        // Only the owner promotes or demotes officers
        Gate::forUser($actor)->authorize('update', $guild);
        abort_if($member->role === 'owner', 422);
        $member->update(['role' => $role]);
        $member->user->notify(new ForumActivity('guild', 'You are now :role of :guild', ['role' => __($role), 'guild' => $guild->name], route('guilds.show', $guild), $actor));
    }

    public function transferOwnership(User $owner, Guild $guild, GuildMember $member): void
    {
        Gate::forUser($owner)->authorize('update', $guild);
        abort_unless($member->guild_id === $guild->id && $member->role !== 'owner', 422);
        DB::transaction(function () use ($guild, $member) {
            $guild->members()->where('role', 'owner')->update(['role' => 'officer']);
            $member->update(['role' => 'owner']);
        });
        $member->user->notify(new ForumActivity('guild', 'You are now the owner of :guild', ['guild' => $guild->name], route('guilds.manage', $guild), $owner));
    }

    public function kick(User $actor, Guild $guild, GuildMember $member): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $guild);
        abort_unless($member->guild_id === $guild->id, 422);
        // Officers can only remove plain members; nobody removes the owner
        abort_if($member->role === 'owner', 422);
        abort_if($member->role === 'officer' && $actor->guildRole($guild) !== 'owner' && ! $actor->isAdmin(), 403);
        $member->delete();
        $member->user->notify(new ForumActivity('guild', 'You were removed from :guild', ['guild' => $guild->name], route('guilds.show', $guild), $actor));
    }

    public function leave(User $user, Guild $guild): void
    {
        $membership = $user->guildMemberships->firstWhere('guild_id', $guild->id);
        abort_unless($membership, 422);
        if ($membership->role === 'owner') {
            throw ValidationException::withMessages(['guild' => __('Transfer the ownership or delete the guild before leaving.')]);
        }
        $membership->delete();
        $user->unsetRelation('guildMemberships');
    }

    public function delete(User $user, Guild $guild): void
    {
        Gate::forUser($user)->authorize('delete', $guild);
        DB::transaction(function () use ($guild) {
            // The forum is kept for moderation history but hidden from everyone but staff
            $guild->section?->update(['guild_id' => null, 'visibility' => 'private', 'name' => $guild->name.' ('.__('closed guild').')']);
            $guild->delete();
        });
    }

    protected function addMember(Guild $guild, User $user): void
    {
        $guild->members()->create(['user_id' => $user->id, 'role' => 'member']);
        $user->unsetRelation('guildMemberships');
        // Other pending applications to the same guild are now pointless
        GuildApplication::where(['user_id' => $user->id, 'guild_id' => $guild->id, 'status' => 'pending'])->update(['status' => 'accepted', 'handled_at' => now()]);
    }

    protected function leaders(Guild $guild)
    {
        return User::whereIn('id', $guild->members()->whereIn('role', ['owner', 'officer'])->select('user_id'))->get();
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'guild';
        $slug = $base;
        for ($i = 2; Guild::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
