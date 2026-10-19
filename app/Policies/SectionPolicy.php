<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    public function view(?User $user, Section $section): bool
    {
        if (! $section->allowsViewFrom($user)) {
            return false;
        }
        if (! $section->isPrivate()) {
            return true;
        }
        if ($section->guild_id && $section->guild?->forum_public) {
            return true;
        }
        if (! $user) {
            return false;
        }
        if ($user->isAdmin() || $user->hasGlobalRole(Role::PRIVATE_MODERATOR)) {
            return true;
        }
        if ($section->guild_id && ($section->guild?->forum_public || in_array($section->guild_id, $user->guildIds(), true))) {
            return true;
        }

        // Any role scoped to this private section grants read access
        return $user->roleAssignments()->contains(
            fn (Role $r) => $r->pivot->scope_type === 'section' && (int) $r->pivot->scope_id === $section->id
        );
    }

    public function moderate(User $user, Section $section): bool
    {
        if ($user->isBanned()) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        if ($section->isPrivate()) {
            if ($user->hasRole(Role::PRIVATE_MODERATOR, $section)) {
                return true;
            }
        } elseif ($user->hasGlobalRole(Role::SENIOR_MODERATOR)) {
            return true;
        }

        // Guild owners and officers keep order in their own guild forum only
        if ($section->guild_id && $section->guild && in_array($user->guildRole($section->guild), ['owner', 'officer'], true)) {
            return true;
        }

        return $user->hasRole(Role::MODERATOR, $section) || $user->hasRole(Role::SECTION_OWNER, $section);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Section $section): bool
    {
        return $user->isAdmin() || ($user->hasRole(Role::SECTION_OWNER, $section) && ! $user->isBanned());
    }

    public function manageModerators(User $user, Section $section): bool
    {
        return $this->update($user, $section);
    }

    public function delete(User $user, Section $section): bool
    {
        return $user->isAdmin();
    }

    public function createThread(User $user, Section $section): bool
    {
        return $user->canParticipate() && $this->view($user, $section) && $this->canWriteInGuildForum($user, $section)
            && $section->allowsThreadsFrom($user) && $this->canWriteInArchive($user, $section);
    }

    // Archived sections stay readable, but only their moderators can still write in them
    public function canWriteInArchive(User $user, Section $section): bool
    {
        return ! $section->is_archived || $this->moderate($user, $section);
    }

    // A public guild forum can be read by anyone but only members and staff write in it
    public function canWriteInGuildForum(User $user, Section $section): bool
    {
        return ! $section->guild_id || in_array($section->guild_id, $user->guildIds(), true) || $this->moderate($user, $section);
    }
}
