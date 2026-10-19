<?php

namespace App\Services;

use App\Models\Poll;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

// Polls, solutions, prefixes, bookmarks and ignore lists
class CommunityToolsService
{
    public function vote(User $user, Poll $poll, array $optionIds): void
    {
        Gate::forUser($user)->authorize('vote', $poll);
        $valid = $poll->options()->whereIn('id', $optionIds)->pluck('id')->unique()->values();
        if ($valid->isEmpty()) {
            throw ValidationException::withMessages(['pollChoices' => __('Pick at least one answer.')]);
        }
        if (! $poll->multiple && $valid->count() > 1) {
            throw ValidationException::withMessages(['pollChoices' => __('This poll accepts only one answer.')]);
        }
        if ($poll->max_choices && $valid->count() > $poll->max_choices) {
            throw ValidationException::withMessages(['pollChoices' => __('Pick at most :count answers.', ['count' => $poll->max_choices])]);
        }
        if (! $poll->allow_change && $poll->votes()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['pollChoices' => __('Votes cannot be changed in this poll.')]);
        }
        DB::transaction(function () use ($user, $poll, $valid) {
            $poll->votes()->where('user_id', $user->id)->delete();
            foreach ($valid as $optionId) {
                $poll->votes()->create(['poll_option_id' => $optionId, 'user_id' => $user->id]);
            }
        });
    }

    public function retractVote(User $user, Poll $poll): void
    {
        Gate::forUser($user)->authorize('vote', $poll);
        abort_unless($poll->allow_change, 403);
        $poll->votes()->where('user_id', $user->id)->delete();
    }

    public function closePoll(User $user, Poll $poll): void
    {
        Gate::forUser($user)->authorize('close', $poll);
        $poll->update(['closes_at' => now()]);
    }

    public function markSolution(User $user, Thread $thread, ?Post $post): void
    {
        Gate::forUser($user)->authorize('solve', $thread);
        if ($post && $post->thread_id !== $thread->id) {
            abort(404);
        }
        $thread->forceFill(['solution_post_id' => $post?->id])->saveQuietly();
    }

    public function setPrefix(User $user, Thread $thread, ?string $prefix): void
    {
        Gate::forUser($user)->authorize('update', $thread);
        $thread->forceFill(['prefix' => array_key_exists((string) $prefix, Thread::PREFIXES) ? $prefix : null])->saveQuietly();
    }

    // Returns true when the post is now bookmarked
    public function toggleBookmark(User $user, Post $post): bool
    {
        Gate::forUser($user)->authorize('view', $post);
        if ($user->bookmarks()->whereKey($post->id)->exists()) {
            $user->bookmarks()->detach($post->id);

            return false;
        }
        $user->bookmarks()->attach($post->id, ['created_at' => now()]);

        return true;
    }

    // Returns true when the other user is now blocked
    public function toggleBlock(User $user, User $other): bool
    {
        abort_if($user->id === $other->id, 422);
        if ($user->hasBlocked($other)) {
            $user->blockedUsers()->detach($other->id);
            $user->flushBlockCache();

            return false;
        }
        // Moderation must always be able to reach users
        if ($other->isStaff()) {
            throw ValidationException::withMessages(['block' => __('Staff members cannot be blocked.')]);
        }
        DB::transaction(function () use ($user, $other) {
            $user->blockedUsers()->attach($other->id, ['created_at' => now()]);
            app(FriendService::class)->remove($user, $other);
        });
        $user->flushBlockCache();

        return true;
    }

    // Returns true when the other user is now ignored
    public function toggleIgnore(User $user, User $other): bool
    {
        abort_if($user->id === $other->id, 422);
        // Staff cannot be ignored so their messages always get through
        if ($other->isStaff() && ! $user->ignores($other)) {
            throw ValidationException::withMessages(['ignore' => __('Staff members cannot be ignored.')]);
        }
        if ($user->ignores($other)) {
            $user->ignoredUsers()->detach($other->id);

            return false;
        }
        $user->ignoredUsers()->attach($other->id, ['created_at' => now()]);

        return true;
    }
}
