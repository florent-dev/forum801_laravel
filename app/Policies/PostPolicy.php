<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PostPolicy
{
    public function view(?User $user, Post $post): bool
    {
        if (! Gate::forUser($user)->allows('view', $post->thread)) {
            return false;
        }
        if ($post->trashed()) {
            return $user && $this->hide($user, $post);
        }
        if (! $post->is_hidden) {
            return true;
        }

        return $user && ($post->user_id === $user->id || $this->hide($user, $post));
    }

    public function update(User $user, Post $post): bool
    {
        if ($this->hide($user, $post)) {
            return true;
        }

        return $post->user_id === $user->id && $user->canParticipate() && ! $post->is_hidden && ! $post->trashed() && ! $post->thread->is_locked && ! $post->thread->trashed() && $this->withinEditWindow($post);
    }

    // Authors may only edit for a limited time when Admin › Settings sets a window
    protected function withinEditWindow(Post $post): bool
    {
        $minutes = \App\Support\ForumSettings::get('edit_window_minutes');

        return ! $minutes || $post->created_at->gt(now()->subMinutes((int) $minutes));
    }

    // Moderated content is only readable by its author and moderators
    public function viewContent(?User $user, Post $post): bool
    {
        if (! $post->moderated_at) {
            return true;
        }

        return $user && ($post->user_id === $user->id || $this->hide($user, $post));
    }

    // Edit history is for the author and moderators
    public function viewHistory(User $user, Post $post): bool
    {
        return $post->user_id === $user->id || $this->hide($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->hide($user, $post);
    }

    public function restore(User $user, Post $post): bool
    {
        return $this->hide($user, $post);
    }

    public function hide(User $user, Post $post): bool
    {
        return Gate::forUser($user)->allows('moderate', $post->thread->section);
    }

    public function react(User $user, Post $post): bool
    {
        return $user->canParticipate() && ! $post->is_hidden && ! $post->trashed() && ! ($post->user && $post->user->hasBlocked($user)) && ! $post->thread->trashed() && Gate::forUser($user)->allows('view', $post->thread);
    }

    public function report(User $user, Post $post): bool
    {
        // Muted members may still report, suspended or unconfirmed ones may not
        return ! $user->isBanned() && $user->hasVerifiedEmail() && $post->user_id !== $user->id && $this->view($user, $post);
    }
}
