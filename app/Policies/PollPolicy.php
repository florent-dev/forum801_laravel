<?php

namespace App\Policies;

use App\Models\Poll;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PollPolicy
{
    // The author of the post or a moderator can end the poll early
    public function close(User $user, Poll $poll): bool
    {
        return ! $poll->isClosed() && ($poll->post?->user_id === $user->id || Gate::forUser($user)->allows('moderate', $poll->thread->section));
    }

    public function vote(User $user, Poll $poll): bool
    {
        $thread = $poll->thread;

        return $user->canParticipate() && ! $poll->isClosed() && ! $thread->is_locked && ! $thread->trashed()
            && Gate::forUser($user)->allows('view', $thread);
    }
}
