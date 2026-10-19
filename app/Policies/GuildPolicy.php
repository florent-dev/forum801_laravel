<?php

namespace App\Policies;

use App\Models\Guild;
use App\Models\User;

class GuildPolicy
{
    public function create(User $user): bool
    {
        return $user->canParticipate() && $user->canJoinMoreGuilds();
    }

    public function join(User $user, Guild $guild): bool
    {
        return $user->canParticipate() && $user->canJoinMoreGuilds() && ! $user->guildRole($guild) && $guild->recruitment !== 'closed';
    }

    // Edit the public page and settings
    public function update(User $user, Guild $guild): bool
    {
        return $user->guildRole($guild) === 'owner' || $user->isAdmin();
    }

    // Accept applications, kick members
    public function manageMembers(User $user, Guild $guild): bool
    {
        return in_array($user->guildRole($guild), ['owner', 'officer'], true) || $user->isAdmin();
    }

    public function delete(User $user, Guild $guild): bool
    {
        return $this->update($user, $guild);
    }
}
