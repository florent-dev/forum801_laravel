<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\Section;
use App\Models\Thread;
use App\Services\CommunityToolsService;
use App\Services\ForumService;
use App\Services\MarkupRenderer;
use App\Services\ModerationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ThreadView extends Component
{
    use Concerns\BuildsPoll, WithPagination;

    #[Locked]
    public int $threadId;

    // Highest post id rendered; newer posts wait behind the banner
    #[Locked]
    public int $ceilingId = 0;

    public int $newCount = 0;

    public string $replyBody = '';

    public bool $showPreview = false;

    public ?int $editingId = null;

    public string $editBody = '';

    public ?int $reportingId = null;

    public string $reportReason = '';

    public ?int $moveTarget = null;

    public string $renameTitle = '';

    public bool $showDeleted = false;

    // Post to outline, used when reviewing a report
    #[Locked]
    public ?int $highlightPostId = null;

    // reported (moderation review) or targeted (followed a link to the post)
    #[Locked]
    public string $highlightStyle = 'targeted';

    public bool $selectMode = false;

    public array $selected = [];

    // Pending hide/moderate action waiting for its mandatory reason
    public ?string $reasonAction = null;

    public array $reasonPostIds = [];

    public string $reason = '';

    // Selected answers keyed "p{pollId}": a list for multiple choice, one id for single choice.
    // String keys keep it a JSON object; a missing or non-list value would turn every checkbox into one shared boolean
    public array $ballot = [];

    // Locked: only showHistory(), which authorizes, may choose the post
    #[Locked]
    public ?int $historyPostId = null;

    // Last post the member had read when opening the thread, marks the "new" divider
    #[Locked]
    public int $readMarkId = 0;

    public function mount(Thread $thread, ?int $highlightPostId = null, string $highlightStyle = 'targeted'): void
    {
        $this->highlightStyle = $highlightStyle === 'reported' ? 'reported' : 'targeted';
        $this->authorize('view', $thread);
        $this->threadId = $thread->id;
        $this->highlightPostId = $highlightPostId;
        if (auth()->check()) {
            $this->readMarkId = (int) \Illuminate\Support\Facades\DB::table('thread_reads')
                ->where(['user_id' => auth()->id(), 'thread_id' => $thread->id])->value('last_read_post_id');
        }
        // A reported post that was deleted is still shown to the reviewing moderator
        if ($highlightPostId && Post::onlyTrashed()->whereKey($highlightPostId)->exists() && Gate::allows('moderate', $thread->section)) {
            $this->showDeleted = true;
        }
        $this->ceilingId = (int) $this->visiblePosts()->max('id');
        $this->renameTitle = $thread->title;
        // Pre-select the answers the member already voted for
        if (auth()->check()) {
            $votes = \App\Models\PollVote::whereIn('poll_id', \App\Models\Poll::where('thread_id', $thread->id)->select('id'))
                ->where('user_id', auth()->id())->get(['poll_id', 'poll_option_id']);
            foreach ($votes->groupBy('poll_id') as $pollId => $rows) {
                $this->ballot['p'.$pollId] = $rows->pluck('poll_option_id')->map(fn ($id) => (string) $id)->all();
            }
        }
        $this->moveTarget = $thread->section_id;
    }

    protected function thread(): Thread
    {
        return Thread::withTrashed()->with('section.guild')->findOrFail($this->threadId);
    }

    protected function visiblePosts(): Builder
    {
        $thread = $this->thread();
        $query = Post::query()->where('thread_id', $thread->id);
        $user = auth()->user();
        if ($user && $this->showDeleted && Gate::allows('moderate', $thread->section)) {
            $query->withTrashed();
        }
        if (! $user || ! Gate::allows('moderate', $thread->section)) {
            $query->where(fn ($q) => $q->where('is_hidden', false)->when($user, fn ($q) => $q->orWhere('user_id', $user->id)));
        }

        return $query;
    }

    // Polling hook: count posts newer than the ceiling without inserting them
    public function checkNew(): void
    {
        $count = $this->visiblePosts()->where('id', '>', $this->ceilingId)->count();
        // Avoid re-rendering the whole page when nothing changed
        if ($count === $this->newCount) {
            $this->skipRender();
        }
        $this->newCount = $count;
    }

    public function showNew(): void
    {
        $this->ceilingId = (int) $this->visiblePosts()->max('id');
        $this->newCount = 0;
        $this->gotoPage($this->lastPage());
        $this->dispatch('scroll-to-post', id: $this->ceilingId);
    }

    protected function lastPage(): int
    {
        return max(1, (int) ceil($this->visiblePosts()->where('id', '<=', $this->ceilingId)->count() / Post::PER_PAGE));
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;
    }

    public function reply(ForumService $forum): void
    {
        $this->authorize('reply', $this->thread());
        $this->validate(['replyBody' => ['required', 'string', 'min:2', 'max:'.\App\Support\ForumSettings::maxPostLength()]] + $this->pollRules(), [], $this->pollAttributes());
        $post = $forum->reply(auth()->user(), $this->thread(), $this->replyBody, $this->pollPayload(), 'replyBody');
        // You are reading the thread and just wrote in it: nothing above is "new" any more
        $this->readMarkId = $post->id;
        $this->reset('replyBody', 'showPreview');
        $this->resetPoll();
        $this->dispatch('draft-sent', key: 'reply-'.$this->threadId);
        $this->ceilingId = $post->id;
        $this->newCount = 0;
        $this->gotoPage($this->lastPage());
        $this->dispatch('scroll-to-post', id: $post->id);
        $this->dispatch('thread-updated');
    }

    public function quote(int $postId): void
    {
        $post = $this->findPost($postId);
        $this->authorize('view', $post);
        $this->authorize('viewContent', $post);
        $this->dispatch('quote-post', body: $post->body, author: $post->user?->name ?? 'someone');
    }

    public function react(int $postId, string $type): void
    {
        abort_unless(array_key_exists($type, Reaction::TYPES), 422);
        $post = $this->findPost($postId);
        $this->authorize('react', $post);
        $existing = Reaction::where(['post_id' => $post->id, 'user_id' => auth()->id(), 'type' => $type])->first();
        $existing ? $existing->delete() : Reaction::create(['post_id' => $post->id, 'user_id' => auth()->id(), 'type' => $type]);
    }

    public function startEdit(int $postId): void
    {
        $post = $this->findPost($postId);
        $this->authorize('update', $post);
        $this->editingId = $post->id;
        $this->editBody = $post->body;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editBody');
    }

    public function saveEdit(ForumService $forum): void
    {
        $post = $this->findPost((int) $this->editingId);
        $this->authorize('update', $post);
        $this->validate(['editBody' => ['required', 'string', 'min:2', 'max:'.\App\Support\ForumSettings::maxPostLength()]]);
        $forum->editPost(auth()->user(), $post, $this->editBody, 'editBody');
        $this->cancelEdit();
        $this->dispatch('toast', message: __('Post updated.'));
    }

    public function startReport(int $postId): void
    {
        $this->authorize('report', $this->findPost($postId));
        $this->reportingId = $postId;
        $this->reportReason = '';
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'report-post');
    }

    public function submitReport(): void
    {
        $post = $this->findPost((int) $this->reportingId);
        $this->authorize('report', $post);
        $this->validate(['reportReason' => ['required', 'string', 'min:3', 'max:1000']]);
        $already = Report::where(['post_id' => $post->id, 'reporter_id' => auth()->id(), 'status' => 'open'])->exists();
        if (! $already) {
            Report::create(['post_id' => $post->id, 'reporter_id' => auth()->id(), 'reason' => $this->reportReason]);
        }
        $this->reset('reportingId', 'reportReason');
        $this->dispatch('close-modal', name: 'report-post');
        $this->dispatch('toast', message: $already ? __('You already reported this post.') : __('Thanks, the moderators have been notified.'));
    }

    public function cancelReport(): void
    {
        $this->reset('reportingId', 'reportReason');
    }

    public function toggleHidden(int $postId, ModerationService $mod): void
    {
        $post = $this->findPost($postId);
        if (! $post->is_hidden) {
            $this->askReason('hide', [$post->id]);

            return;
        }
        $mod->setHidden(auth()->user(), $post, false);
        $this->dispatch('toast', message: __('Post is visible again.'));
    }

    public function toggleModerated(int $postId, ModerationService $mod): void
    {
        $post = $this->findPost($postId);
        if (! $post->moderated_at) {
            $this->askReason('moderate', [$post->id]);

            return;
        }
        $mod->setModerated(auth()->user(), $post, false);
        $this->dispatch('toast', message: __('Moderation note removed.'));
    }

    // Open the reason dialog for one post or for the current selection
    protected function askReason(string $action, array $postIds): void
    {
        $this->authorize('moderate', $this->thread());
        $this->reasonAction = $action;
        $this->reasonPostIds = array_map('intval', $postIds);
        $this->reason = '';
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'post-reason');
    }

    public function confirmReason(ModerationService $mod): void
    {
        abort_unless(in_array($this->reasonAction, ['hide', 'moderate'], true), 422);
        $this->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $posts = Post::withTrashed()->with('thread.section', 'user')->where('thread_id', $this->threadId)->whereIn('id', $this->reasonPostIds)->get();
        $count = $mod->bulkPosts(auth()->user(), $posts, $this->reasonAction, trim($this->reason));
        $messages = ['hide' => ':count post hidden.|:count posts hidden.', 'moderate' => ':count post moderated.|:count posts moderated.'];
        $this->dispatch('toast', message: trans_choice($messages[$this->reasonAction], $count));
        $this->reset('reasonAction', 'reasonPostIds', 'reason');
        $this->selected = [];
        $this->dispatch('close-modal', name: 'post-reason');
        $this->dispatch('thread-updated');
    }

    public function toggleSubscription(ForumService $forum): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $thread = $this->thread();
        $this->authorize('view', $thread);
        if ($user->isSubscribedTo($thread)) {
            $forum->unsubscribe($user, $thread);
            $this->dispatch('toast', message: __('You will no longer be notified about this thread.'), type: 'info');
        } else {
            $forum->subscribe($user, $thread);
            $this->dispatch('toast', message: __('You are now following this thread.'));
        }
    }

    public function toggleLock(ModerationService $mod): void
    {
        $thread = $this->thread();
        $mod->setLocked(auth()->user(), $thread, ! $thread->is_locked);
        $this->dispatch('toast', message: $thread->is_locked ? __('Thread locked.') : __('Thread unlocked.'));
        $this->dispatch('thread-updated');
    }

    public function togglePin(ModerationService $mod): void
    {
        $thread = $this->thread();
        $mod->setPinned(auth()->user(), $thread, ! $thread->is_pinned);
        $this->dispatch('toast', message: $thread->is_pinned ? __('Thread pinned.') : __('Thread unpinned.'));
        $this->dispatch('thread-updated');
    }

    public function move(ModerationService $mod)
    {
        $target = Section::findOrFail((int) $this->moveTarget);
        $thread = $this->thread();
        if ($target->id === $thread->section_id) {
            return null;
        }
        $mod->move(auth()->user(), $thread, $target);
        session()->flash('toast', __('Thread moved to :section.', ['section' => $target->name]));

        return $this->redirect($thread->fresh()->url(), navigate: true);
    }

    public function rename(ModerationService $mod): void
    {
        $this->validate(['renameTitle' => ['required', 'string', 'min:3', 'max:120']]);
        $mod->rename(auth()->user(), $this->thread(), trim($this->renameTitle));
        $this->dispatch('close-modal', name: 'rename-thread');
        $this->dispatch('toast', message: __('Thread renamed.'));
        $this->dispatch('thread-updated');
    }

    public function toggleShowDeleted(): void
    {
        $this->authorize('moderate', $this->thread());
        $this->showDeleted = ! $this->showDeleted;
        $this->ceilingId = (int) $this->visiblePosts()->max('id');
    }

    public function toggleSelectMode(): void
    {
        $this->authorize('moderate', $this->thread());
        $this->selectMode = ! $this->selectMode;
        $this->selected = [];
    }

    // Apply a moderation action to every selected post
    public function bulk(string $action, ModerationService $mod): void
    {
        abort_unless(in_array($action, ['hide', 'unhide', 'moderate', 'unmoderate', 'delete', 'restore'], true), 422);
        $this->authorize('moderate', $this->thread());
        $ids = array_map('intval', $this->selected);
        if (! $ids) {
            $this->dispatch('toast', message: __('Select at least one post.'), type: 'error');

            return;
        }
        // These two need a reason first
        if (in_array($action, ['hide', 'moderate'], true)) {
            $this->askReason($action, $ids);

            return;
        }
        $posts = Post::withTrashed()->with('thread.section', 'user')->where('thread_id', $this->threadId)->whereIn('id', $ids)->get();
        $count = $mod->bulkPosts(auth()->user(), $posts, $action);
        $this->selected = [];
        if (in_array($action, ['delete', 'restore'], true)) {
            $this->ceilingId = max($this->ceilingId, (int) $this->visiblePosts()->max('id'));
        }
        $this->dispatch('thread-updated');
        $messages = [
            'unhide' => ':count post made visible.|:count posts made visible.',
            'unmoderate' => ':count moderation note removed.|:count moderation notes removed.',
            'delete' => ':count post deleted.|:count posts deleted.',
            'restore' => ':count post restored.|:count posts restored.',
        ];
        $this->dispatch('toast', message: trans_choice($messages[$action], $count));
    }

    public function deletePost(int $postId, ModerationService $mod): void
    {
        $mod->deletePost(auth()->user(), $this->findPost($postId));
        $this->dispatch('thread-updated');
        $this->dispatch('toast', message: __('Post deleted.'));
    }

    public function restorePost(int $postId, ModerationService $mod): void
    {
        $mod->restorePost(auth()->user(), $this->findPost($postId));
        $this->dispatch('thread-updated');
        $this->dispatch('toast', message: __('Post restored.'));
    }

    protected function findPoll(int $pollId): \App\Models\Poll
    {
        return \App\Models\Poll::with(['thread.section', 'post'])->where('thread_id', $this->threadId)->findOrFail($pollId);
    }

    // Give every displayed poll a ballot of the right shape before it is bound to inputs
    protected function preparePolls($polls)
    {
        foreach ($polls as $poll) {
            $key = 'p'.$poll->id;
            $current = $this->ballot[$key] ?? null;
            if ($poll->multiple) {
                $this->ballot[$key] = is_array($current) ? array_values(array_map('strval', $current)) : [];
            } else {
                $this->ballot[$key] = is_array($current) ? (string) ($current[0] ?? '') : (string) ($current ?? '');
            }
        }

        return $polls;
    }

    public function vote(int $pollId, CommunityToolsService $tools): void
    {
        $poll = $this->findPoll($pollId);
        $ids = array_values(array_filter(array_map('intval', (array) ($this->ballot['p'.$pollId] ?? []))));
        $tools->vote(auth()->user(), $poll, $ids);
        $this->dispatch('toast', message: __('Your vote has been saved.'));
    }

    public function retractVote(int $pollId, CommunityToolsService $tools): void
    {
        $tools->retractVote(auth()->user(), $this->findPoll($pollId));
        $poll = $this->findPoll($pollId);
        $this->ballot['p'.$pollId] = $poll->multiple ? [] : '';
        $this->dispatch('toast', message: __('Your vote has been removed.'), type: 'info');
    }

    public function closePoll(int $pollId, CommunityToolsService $tools): void
    {
        $tools->closePoll(auth()->user(), $this->findPoll($pollId));
        $this->dispatch('toast', message: __('Poll closed.'));
    }

    public function markSolution(int $postId, CommunityToolsService $tools): void
    {
        $thread = $this->thread();
        $post = $this->findPost($postId);
        $unmark = $thread->solution_post_id === $post->id;
        $tools->markSolution(auth()->user(), $thread, $unmark ? null : $post);
        $this->dispatch('thread-updated');
        $this->dispatch('toast', message: $unmark ? __('Solution removed.') : __('Marked as the solution.'));
    }

    public function setPrefix(string $prefix, CommunityToolsService $tools): void
    {
        $tools->setPrefix(auth()->user(), $this->thread(), $prefix ?: null);
        $this->dispatch('thread-updated');
        $this->dispatch('toast', message: __('Thread prefix updated.'));
    }

    public function toggleBlock(int $userId, CommunityToolsService $tools): void
    {
        abort_unless(auth()->check(), 403);
        $blocked = $tools->toggleBlock(auth()->user(), \App\Models\User::findOrFail($userId));
        $this->dispatch('toast', message: $blocked ? __('User blocked. They can no longer contact you.') : __('User unblocked.'), type: 'info');
    }

    public function toggleBookmark(int $postId, CommunityToolsService $tools): void
    {
        abort_unless(auth()->check(), 403);
        $saved = $tools->toggleBookmark(auth()->user(), $this->findPost($postId));
        $this->dispatch('toast', message: $saved ? __('Post bookmarked.') : __('Bookmark removed.'), type: $saved ? 'success' : 'info');
    }

    public function showHistory(int $postId): void
    {
        $this->authorize('viewHistory', $this->findPost($postId));
        $this->historyPostId = $postId;
        $this->dispatch('open-modal', name: 'post-history');
    }

    public function restoreThread(ModerationService $mod): void
    {
        $mod->restoreThread(auth()->user(), $this->thread());
        $this->dispatch('thread-updated');
        $this->dispatch('toast', message: __('Thread restored.'));
    }

    public function deleteThread(ModerationService $mod)
    {
        $thread = $this->thread();
        $section = $thread->section;
        $mod->deleteThread(auth()->user(), $thread);
        session()->flash('toast', __('Thread deleted.'));

        return $this->redirect(route('sections.show', $section), navigate: true);
    }

    protected function findPost(int $id): Post
    {
        return Post::withTrashed()->with('thread.section', 'user')->where('thread_id', $this->threadId)->findOrFail($id);
    }

    public function paginationView(): string
    {
        return 'partials.pager';
    }

    public function render(MarkupRenderer $renderer, ForumService $forum)
    {
        $thread = $this->thread();
        $this->authorize('view', $thread);
        $posts = $this->visiblePosts()
            ->where('id', '<=', $this->ceilingId)
            ->with(['user' => fn ($q) => $q->with($thread->section->guild_id ? ['roles', 'guildMemberships'] : ['roles']), 'reactions.user:id,name', 'deleter:id,name', 'moderator:id,name', 'hider:id,name'])
            ->orderBy('id')
            ->paginate(Post::PER_PAGE);

        if (auth()->check() && $posts->count()) {
            $forum->markRead(auth()->user(), $thread, (int) $posts->max('id'));
        }

        $user = auth()->user();
        $canModerate = $user && Gate::allows('moderate', $thread->section);
        $moveTargets = $canModerate
            ? Section::orderBy('position')->get()->filter(fn ($s) => Gate::allows('moderate', $s))
            : collect();

        return view('livewire.thread-view', [
            'thread' => $thread->loadMissing('deleter:id,name'),
            'posts' => $posts,
            'canModerate' => $canModerate,
            'canReply' => $user && Gate::allows('reply', $thread),
            'subscribed' => $user?->isSubscribedTo($thread) ?? false,
            'polls' => $polls = $this->preparePolls(\App\Models\Poll::with(['options' => fn ($q) => $q->withCount('votes')])
                ->whereIn('post_id', $posts->pluck('id'))->get()->keyBy('post_id')),
            'myVotes' => $user && $polls->isNotEmpty()
                ? \App\Models\PollVote::whereIn('poll_id', $polls->pluck('id'))->where('user_id', $user->id)->get()->groupBy('poll_id')->map->pluck('poll_option_id')->map->all()->all()
                : [],
            'voterCounts' => $polls->isNotEmpty() ? \App\Models\PollVote::whereIn('poll_id', $polls->pluck('id'))->selectRaw('poll_id, count(distinct user_id) as voters')->groupBy('poll_id')->pluck('voters', 'poll_id')->all() : [],
            'voterNames' => $polls->where('show_voters', true)->isNotEmpty()
                ? \App\Models\PollVote::with('user:id,name')->whereIn('poll_id', $polls->where('show_voters', true)->pluck('id'))->get()->groupBy('poll_option_id')->map(fn ($votes) => $votes->pluck('user.name')->filter()->all())->all()
                : [],
            'bookmarked' => $user ? $user->bookmarks()->whereIn('posts.id', $posts->pluck('id'))->pluck('posts.id')->all() : [],
            'ignored' => $user ? $user->mutedAuthorIds() : [],
            'history' => $this->historyPostId ? \App\Models\PostRevision::with('user')->where('post_id', $this->historyPostId)->whereHas('post', fn ($q) => $q->withTrashed()->where('thread_id', $thread->id))->latest('id')->get() : collect(),
            'prefixes' => array_map(fn ($label) => __($label), Thread::PREFIXES),
            'moveTargets' => $moveTargets,
            'preview' => $this->showPreview ? $renderer->render($this->replyBody) : null,
            'reactionTypes' => Reaction::TYPES,
        ]);
    }
}
