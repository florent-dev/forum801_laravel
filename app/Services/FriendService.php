<?php

namespace App\Services;

use App\Models\Friendship;
use App\Models\User;
use App\Notifications\ForumActivity;
use Illuminate\Validation\ValidationException;

class FriendService
{
    // Sends a request, or accepts the pending one coming from the other user
    public function request(User $user, User $other): Friendship
    {
        abort_if($user->id === $other->id, 422);
        if ($other->ignores($user) || $user->isBlockedWith($other)) {
            throw ValidationException::withMessages(['friend' => __('This user does not accept friend requests from you.')]);
        }
        $incoming = Friendship::where(['user_id' => $other->id, 'friend_id' => $user->id])->first();
        if ($incoming) {
            return $this->accept($user, $incoming);
        }
        $friendship = Friendship::firstOrCreate(['user_id' => $user->id, 'friend_id' => $other->id]);
        if ($friendship->wasRecentlyCreated) {
            $other->notify(new ForumActivity('friend', ':actor wants to be your friend', ['actor' => $user->name], route('friends'), $user));
        }

        return $friendship;
    }

    public function accept(User $user, Friendship $friendship): Friendship
    {
        abort_unless($friendship->friend_id === $user->id, 403);
        if (! $friendship->accepted_at) {
            $friendship->update(['accepted_at' => now()]);
            $friendship->requester->notify(new ForumActivity('friend', ':actor accepted your friend request', ['actor' => $user->name], route('profile', $user), $user));
        }
        $user->flushFriendCache();

        return $friendship;
    }

    // Declines a request, cancels one, or ends a friendship
    public function remove(User $user, User $other): void
    {
        Friendship::where(fn ($q) => $q->where(['user_id' => $user->id, 'friend_id' => $other->id]))
            ->orWhere(fn ($q) => $q->where(['user_id' => $other->id, 'friend_id' => $user->id]))
            ->delete();
        $user->flushFriendCache();
    }

    // none, friends, sent or received
    public function status(User $user, User $other): string
    {
        $row = Friendship::where(fn ($q) => $q->where(['user_id' => $user->id, 'friend_id' => $other->id]))
            ->orWhere(fn ($q) => $q->where(['user_id' => $other->id, 'friend_id' => $user->id]))
            ->first();

        return match (true) {
            $row === null => 'none',
            $row->accepted_at !== null => 'friends',
            $row->user_id === $user->id => 'sent',
            default => 'received',
        };
    }
}
