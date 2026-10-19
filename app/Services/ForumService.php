<?php

namespace App\Services;

use App\Models\Post;
use App\Notifications\ForumActivity;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use App\Support\ForumSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForumService
{
    public function __construct(protected MarkupRenderer $renderer, protected WordFilterService $words)
    {
    }

    // Poll format: question, options[], multiple, max_choices, closes_at (or days), results, show_voters, allow_change
    public function createThread(User $user, Section $section, string $title, string $body, ?string $prefix = null, ?array $poll = null): Thread
    {
        $this->ensurePollsAllowed($section, $poll);
        [$title, $flaggedTitle] = $this->words->apply($title, 'title');
        [$body, $flagged] = $this->words->apply($body);
        $this->throttle($user, 'body');

        return DB::transaction(function () use ($user, $section, $title, $body, $prefix, $poll, $flaggedTitle, $flagged) {
            $thread = Thread::create([
                'section_id' => $section->id,
                'user_id' => $user->id,
                'title' => $title,
                'slug' => Str::slug($title) ?: 'thread',
                'prefix' => array_key_exists((string) $prefix, Thread::PREFIXES) ? $prefix : null,
                'last_post_at' => now(),
            ]);
            $post = $this->storePost($user, $thread, $body, $poll);
            $this->flagForReview($post, array_merge($flaggedTitle, $flagged));

            return $thread->fresh();
        });
    }

    public function reply(User $user, Thread $thread, string $body, ?array $poll = null, string $field = 'body'): Post
    {
        $this->ensurePollsAllowed($thread->section, $poll);
        [$body, $flagged] = $this->words->apply($body, $field);
        $this->throttle($user, $field);

        return DB::transaction(function () use ($user, $thread, $body, $poll, $flagged) {
            $post = $this->storePost($user, $thread, $body, $poll);
            $this->flagForReview($post, $flagged);

            return $post;
        });
    }

    public function editPost(User $editor, Post $post, string $body, string $field = 'body'): Post
    {
        // Moderators editing someone else's post are not filtered
        if ($editor->id === $post->user_id) {
            [$body, $flagged] = $this->words->apply($body, $field);
            $this->flagForReview($post, $flagged);
        }
        // Keep the previous version for the edit history
        if ($post->body !== $body) {
            $post->revisions()->create(['user_id' => $editor->id, 'body' => $post->body]);
        }
        $post->update([
            'body' => $body,
            'body_html' => $this->renderer->render($body),
            'edited_at' => now(),
        ]);

        return $post;
    }

    // Mark a thread as read up to the given post for this user
    public function markRead(User $user, Thread $thread, int $postId): void
    {
        DB::table('thread_reads')->upsert(
            [['user_id' => $user->id, 'thread_id' => $thread->id, 'last_read_post_id' => $postId, 'read_at' => now()]],
            ['user_id', 'thread_id'],
            ['last_read_post_id' => DB::raw('GREATEST(thread_reads.last_read_post_id, excluded.last_read_post_id)'), 'read_at' => now()]
        );
    }

    protected function attachPoll(Thread $thread, Post $post, array $poll): void
    {
        $closesAt = $poll['closes_at'] ?? (! empty($poll['days']) ? now()->addDays((int) $poll['days']) : null);
        $created = $post->poll()->create([
            'thread_id' => $thread->id,
            'question' => $poll['question'],
            'multiple' => (bool) ($poll['multiple'] ?? false),
            'max_choices' => $poll['max_choices'] ?? null,
            'closes_at' => $closesAt,
            'results_visibility' => in_array($poll['results'] ?? 'always', \App\Models\Poll::RESULTS, true) ? ($poll['results'] ?? 'always') : 'always',
            'show_voters' => (bool) ($poll['show_voters'] ?? false),
            'allow_change' => (bool) ($poll['allow_change'] ?? true),
        ]);
        foreach (array_values($poll['options']) as $i => $label) {
            $created->options()->create(['label' => $label, 'position' => $i]);
        }
    }

    public function subscribe(User $user, Thread $thread): void
    {
        DB::table('thread_subscriptions')->insertOrIgnore(['user_id' => $user->id, 'thread_id' => $thread->id, 'created_at' => now()]);
    }

    public function unsubscribe(User $user, Thread $thread): void
    {
        DB::table('thread_subscriptions')->where(['user_id' => $user->id, 'thread_id' => $thread->id])->delete();
    }

    // Mark every visible thread as read for the user, optionally in one section
    public function markAllRead(User $user, ?Section $section = null): void
    {
        $threads = Thread::query()
            ->whereIn('section_id', $section ? [$section->id] : Section::visibleTo($user)->select('id'))
            ->whereNotNull('last_post_id')
            ->get(['id', 'last_post_id']);
        $rows = $threads->map(fn ($t) => ['user_id' => $user->id, 'thread_id' => $t->id, 'last_read_post_id' => $t->last_post_id, 'read_at' => now()])->all();
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('thread_reads')->upsert($chunk, ['user_id', 'thread_id'], ['last_read_post_id', 'read_at']);
        }
    }

    // Notify subscribers, mentioned and quoted users about a new post
    protected function notifyAbout(User $author, Thread $thread, Post $post, bool $isNewThread): void
    {
        $url = route('posts.show', $post);
        $notified = [$author->id => true];
        $canSee = fn (User $u) => ! $u->isBanned() && Gate::forUser($u)->allows('view', $thread);

        $mentioned = $this->renderer->mentionedNames($post->body);
        preg_match_all('/^>\s*\*\*([A-Za-z0-9_.\-]+)\*\* wrote:/m', $post->body, $q);
        $quoted = array_unique($q[1] ?? []);

        foreach (User::whereIn('name', $quoted)->get() as $u) {
            if (! isset($notified[$u->id]) && $canSee($u)) {
                $u->notify(new ForumActivity('quote', ':actor quoted you in “:title”', ['actor' => $author->name, 'title' => $thread->title], $url, $author));
                $notified[$u->id] = true;
            }
        }
        foreach (User::whereIn('name', $mentioned)->get() as $u) {
            // Respect the mention preference and the ignore list of the mentioned user
            if (! isset($notified[$u->id]) && $canSee($u) && $u->acceptsMentionFrom($author) && ! $u->ignores($author)) {
                $u->notify(new ForumActivity('mention', ':actor mentioned you in “:title”', ['actor' => $author->name, 'title' => $thread->title], $url, $author));
                $notified[$u->id] = true;
            }
        }
        if ($isNewThread) {
            return;
        }
        $subscribers = User::whereIn('id', DB::table('thread_subscriptions')->where('thread_id', $thread->id)->select('user_id'))->get();
        foreach ($subscribers as $u) {
            if (! isset($notified[$u->id]) && $canSee($u)) {
                $u->notify(new ForumActivity('reply', ':actor replied to “:title”', ['actor' => $author->name, 'title' => $thread->title], $url, $author));
            }
        }
    }

    protected function ensurePollsAllowed(Section $section, ?array $poll): void
    {
        if ($poll && ! $section->polls_enabled) {
            throw ValidationException::withMessages(['withPoll' => __('Polls are disabled in this section.')]);
        }
    }

    protected function storePost(User $user, Thread $thread, string $body, ?array $poll = null): Post
    {
        $isNewThread = (int) $thread->posts_count === 0;
        $post = $thread->posts()->create([
            'user_id' => $user->id,
            'ip' => request()->ip(),
            'body' => $body,
            'body_html' => $this->renderer->render($body),
        ]);
        $thread->forceFill([
            'posts_count' => $thread->posts_count + 1,
            'last_post_id' => $post->id,
            'last_post_user_id' => $user->id,
            'last_post_at' => $post->created_at,
            'updated_at' => $post->created_at,
        ])->save();
        if ($poll) {
            $this->attachPoll($thread, $post, $poll);
        }
        $this->markRead($user, $thread, $post->id);
        $this->notifyAbout($user, $thread, $post, $isNewThread);
        $this->subscribe($user, $thread);

        return $post;
    }

    // Word filter matches set to “review” open a report without a human reporter
    protected function flagForReview(Post $post, array $phrases): void
    {
        if (! $phrases || $post->reports()->whereNull('reporter_id')->where('status', 'open')->exists()) {
            return;
        }
        $post->reports()->create(['reporter_id' => null, 'reason' => __('Word filter: :words', ['words' => implode(', ', $phrases)])]);
    }

    // Posting rate limit per user, set in Admin › Settings
    protected function throttle(User $user, string $field): void
    {
        $key = 'posting:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, (int) ForumSettings::get('posts_per_minute'))) {
            throw ValidationException::withMessages([
                $field => __('You are posting too fast. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
        RateLimiter::hit($key, 60);
    }
}
