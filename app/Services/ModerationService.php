<?php

namespace App\Services;

use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Report;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\ForumActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ModerationService
{
    public function setLocked(User $mod, Thread $thread, bool $locked): void
    {
        Gate::forUser($mod)->authorize('lock', $thread);
        $thread->forceFill(['is_locked' => $locked])->saveQuietly();
        ModerationLog::record($mod, $locked ? 'thread.lock' : 'thread.unlock', $thread, $thread->section_id, ['title' => $thread->title]);
    }

    public function setPinned(User $mod, Thread $thread, bool $pinned): void
    {
        Gate::forUser($mod)->authorize('pin', $thread);
        $thread->forceFill(['is_pinned' => $pinned])->saveQuietly();
        ModerationLog::record($mod, $pinned ? 'thread.pin' : 'thread.unpin', $thread, $thread->section_id, ['title' => $thread->title]);
    }

    public function move(User $mod, Thread $thread, Section $target): void
    {
        Gate::forUser($mod)->authorize('move', [$thread, $target]);
        $from = $thread->section_id;
        $thread->forceFill(['section_id' => $target->id])->saveQuietly();
        ModerationLog::record($mod, 'thread.move', $thread, $target->id, ['title' => $thread->title, 'from' => $from, 'to' => $target->id]);
    }

    public function rename(User $mod, Thread $thread, string $title): void
    {
        Gate::forUser($mod)->authorize('update', $thread);
        $old = $thread->title;
        $thread->forceFill(['title' => $title, 'slug' => \Illuminate\Support\Str::slug($title) ?: 'thread'])->saveQuietly();
        if ($thread->user_id !== $mod->id) {
            ModerationLog::record($mod, 'thread.rename', $thread, $thread->section_id, ['from' => $old, 'to' => $title]);
        }
    }

    // Threads are soft deleted so they can be restored later
    public function deleteThread(User $mod, Thread $thread): void
    {
        Gate::forUser($mod)->authorize('delete', $thread);
        if ($thread->trashed()) {
            return;
        }
        $thread->forceFill(['deleted_by' => $mod->id])->saveQuietly();
        $thread->delete();
        ModerationLog::record($mod, 'thread.delete', $thread, $thread->section_id, ['title' => $thread->title, 'author_id' => $thread->user_id]);
    }

    public function restoreThread(User $mod, Thread $thread): void
    {
        Gate::forUser($mod)->authorize('restore', $thread);
        if (! $thread->trashed()) {
            return;
        }
        $thread->restore();
        $thread->forceFill(['deleted_by' => null])->saveQuietly();
        ModerationLog::record($mod, 'thread.restore', $thread, $thread->section_id, ['title' => $thread->title]);
    }

    public function deletePost(User $mod, Post $post): void
    {
        Gate::forUser($mod)->authorize('delete', $post);
        if ($post->trashed()) {
            return;
        }
        DB::transaction(function () use ($mod, $post) {
            $post->forceFill(['deleted_by' => $mod->id])->saveQuietly();
            $post->delete();
            $post->thread->refreshStats();
            ModerationLog::record($mod, 'post.delete', $post, $post->thread->section_id, ['thread_id' => $post->thread_id]);
        });
    }

    public function restorePost(User $mod, Post $post): void
    {
        Gate::forUser($mod)->authorize('restore', $post);
        if (! $post->trashed()) {
            return;
        }
        DB::transaction(function () use ($mod, $post) {
            $post->restore();
            $post->forceFill(['deleted_by' => null])->saveQuietly();
            $post->thread->refreshStats();
            ModerationLog::record($mod, 'post.restore', $post, $post->thread->section_id, ['thread_id' => $post->thread_id]);
        });
    }

    // Apply one moderation action to many threads, returns how many were changed
    public function bulkThreads(User $mod, iterable $threads, string $action): int
    {
        $count = 0;
        foreach ($threads as $thread) {
            match ($action) {
                'delete' => $this->deleteThread($mod, $thread),
                'restore' => $this->restoreThread($mod, $thread),
                'lock' => $this->setLocked($mod, $thread, true),
                'unlock' => $this->setLocked($mod, $thread, false),
                'pin' => $this->setPinned($mod, $thread, true),
                'unpin' => $this->setPinned($mod, $thread, false),
            };
            $count++;
        }

        return $count;
    }

    // Apply one moderation action to many posts, returns how many were changed
    public function bulkPosts(User $mod, iterable $posts, string $action, ?string $reason = null): int
    {
        $count = 0;
        foreach ($posts as $post) {
            match ($action) {
                'delete' => $this->deletePost($mod, $post),
                'restore' => $this->restorePost($mod, $post),
                'hide' => $post->is_hidden ? null : $this->setHidden($mod, $post, true, $reason),
                'moderate' => $this->setModerated($mod, $post, true, $reason),
                'unmoderate' => $post->moderated_at ? $this->setModerated($mod, $post, false) : null,
                'unhide' => $post->is_hidden ? $this->setHidden($mod, $post, false) : null,
            };
            $count++;
        }

        return $count;
    }

    // Hiding needs a reason; unhiding clears it
    public function setHidden(User $mod, Post $post, bool $hidden, ?string $reason = null): void
    {
        Gate::forUser($mod)->authorize('hide', $post);
        $this->requireReason($hidden, $reason);
        DB::transaction(function () use ($mod, $post, $hidden, $reason) {
            $post->forceFill([
                'is_hidden' => $hidden,
                'hidden_by' => $hidden ? $mod->id : null,
                'hide_reason' => $hidden ? $reason : null,
            ])->saveQuietly();
            $post->thread->refreshStats();
            ModerationLog::record($mod, $hidden ? 'post.hide' : 'post.unhide', $post, $post->thread->section_id, array_filter([
                'thread_id' => $post->thread_id,
                'reason' => $reason,
            ]));
        });
        // Tell the author why their post was hidden
        if ($hidden && $post->user && $post->user_id !== $mod->id) {
            $post->user->notify(new ForumActivity('moderation', 'Your post in “:title” was hidden: :reason', ['title' => $post->thread->title, 'reason' => $reason], route('posts.show', $post), $mod));
        }
    }

    // A moderated post stays visible to everyone with a public moderation note
    public function setModerated(User $mod, Post $post, bool $moderated, ?string $reason = null): void
    {
        Gate::forUser($mod)->authorize('hide', $post);
        $this->requireReason($moderated, $reason);
        $post->forceFill([
            'moderated_at' => $moderated ? now() : null,
            'moderated_by' => $moderated ? $mod->id : null,
            'moderation_reason' => $moderated ? $reason : null,
        ])->saveQuietly();
        ModerationLog::record($mod, $moderated ? 'post.moderate' : 'post.unmoderate', $post, $post->thread->section_id, array_filter([
            'thread_id' => $post->thread_id,
            'reason' => $reason,
        ]));
        if ($moderated && $post->user && $post->user_id !== $mod->id) {
            $post->user->notify(new ForumActivity('moderation', 'Your post in “:title” was moderated: :reason', ['title' => $post->thread->title, 'reason' => $reason], route('posts.show', $post), $mod));
        }
    }

    protected function requireReason(bool $applying, ?string $reason): void
    {
        if ($applying && trim((string) $reason) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['reason' => __('A reason is required.')]);
        }
    }

    public function resolveReport(User $mod, Report $report, string $status): void
    {
        Gate::forUser($mod)->authorize('resolve', $report);
        $report->update(['status' => $status, 'handled_by' => $mod->id, 'handled_at' => now()]);
        ModerationLog::record($mod, 'report.'.$status, $report, $report->post->thread->section_id, ['post_id' => $report->post_id]);
    }
}
