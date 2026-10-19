<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ThreadPolicy
{
    public function view(?User $user, Thread $thread): bool
    {
        if (! Gate::forUser($user)->allows('view', $thread->section)) {
            return false;
        }

        // Deleted threads are only visible to their moderators
        return ! $thread->trashed() || ($user && $this->moderate($user, $thread));
    }

    public function reply(User $user, Thread $thread): bool
    {
        if (! $user->canParticipate() || $thread->trashed() || ! $this->view($user, $thread)) {
            return false;
        }
        if (! app(SectionPolicy::class)->canWriteInGuildForum($user, $thread->section) || ! app(SectionPolicy::class)->canWriteInArchive($user, $thread->section)) {
            return false;
        }

        return ! $thread->is_locked || $this->moderate($user, $thread);
    }

    public function update(User $user, Thread $thread): bool
    {
        return $this->moderate($user, $thread)
            || ($thread->user_id === $user->id && ! $thread->is_locked && ! $thread->section->is_archived && $user->canParticipate());
    }

    public function moderate(User $user, Thread $thread): bool
    {
        return Gate::forUser($user)->allows('moderate', $thread->section);
    }

    public function lock(User $user, Thread $thread): bool
    {
        return $this->moderate($user, $thread);
    }

    public function pin(User $user, Thread $thread): bool
    {
        return $this->moderate($user, $thread);
    }

    // Moving requires moderation rights on both the source and the target section
    public function move(User $user, Thread $thread, Section $target): bool
    {
        return $this->moderate($user, $thread) && Gate::forUser($user)->allows('moderate', $target);
    }

    public function delete(User $user, Thread $thread): bool
    {
        return $this->moderate($user, $thread);
    }

    // The thread author or a moderator can pick the answer that solved it
    public function solve(User $user, Thread $thread): bool
    {
        return ! $thread->trashed() && ($this->moderate($user, $thread) || ($thread->user_id === $user->id && ! $thread->section->is_archived && $user->canParticipate()));
    }

    public function restore(User $user, Thread $thread): bool
    {
        return $this->moderate($user, $thread);
    }
}
