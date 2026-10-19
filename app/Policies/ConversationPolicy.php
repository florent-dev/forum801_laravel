<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function create(User $user): bool
    {
        return $user->canParticipate();
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        // A user blocked by someone in the conversation can no longer write in it
        return $user->canParticipate() && $this->view($user, $conversation)
            && ! $conversation->activeParticipants()->where('users.id', '!=', $user->id)->get()->contains(fn (User $p) => $p->hasBlocked($user));
    }
}
