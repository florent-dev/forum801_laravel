<?php

namespace App\Livewire;

use App\Models\Section;
use App\Models\Thread;
use App\Services\ModerationService;
use Illuminate\Support\Facades\Gate;
use App\Services\ForumService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ThreadList extends Component
{
    use WithPagination;

    public const PER_PAGE = 25;

    public const SORTS = ['activity' => 'Latest activity', 'newest' => 'Newest threads', 'replies' => 'Most replies', 'views' => 'Most viewed'];

    public Section $section;

    public ?int $activeThreadId = null;

    #[Url(as: 'sort', except: 'activity')]
    public string $sort = 'activity';

    public bool $showDeleted = false;

    public bool $selectMode = false;

    public array $selected = [];

    public function mount(Section $section, ?int $activeThreadId = null): void
    {
        $this->authorize('view', $section);
        $this->section = $section;
        $this->activeThreadId = $activeThreadId;

        // Opening a thread without a list page shows the page that contains it
        if ($activeThreadId && ! request()->has('tp')) {
            $this->setPage($this->pageOfThread($activeThreadId), 'tp');
        }
    }

    protected function orderedQuery()
    {
        $query = $this->section->threads()->orderByDesc('is_pinned');
        match (array_key_exists($this->sort, self::SORTS) ? $this->sort : 'activity') {
            'newest' => $query->orderByDesc('created_at'),
            'replies' => $query->orderByDesc('posts_count'),
            'views' => $query->orderByDesc('views_count'),
            default => $query->orderByDesc('last_post_at'),
        };

        return $query->orderByDesc('id');
    }

    protected function pageOfThread(int $threadId): int
    {
        $position = $this->orderedQuery()->pluck('id')->search($threadId);

        return $position === false ? 1 : intdiv($position, self::PER_PAGE) + 1;
    }

    public function updatedSort(): void
    {
        if (! array_key_exists($this->sort, self::SORTS)) {
            $this->sort = 'activity';
        }
        $this->resetPage('tp');
    }

    public function toggleSelectMode(): void
    {
        $this->authorize('moderate', $this->section);
        $this->selectMode = ! $this->selectMode;
        $this->selected = [];
    }

    public function toggleShowDeleted(): void
    {
        $this->authorize('moderate', $this->section);
        $this->showDeleted = ! $this->showDeleted;
        $this->resetPage('tp');
    }

    // Apply a moderation action to every selected thread
    public function bulk(string $action, ModerationService $mod): void
    {
        abort_unless(in_array($action, ['delete', 'restore', 'lock', 'unlock', 'pin', 'unpin'], true), 422);
        $this->authorize('moderate', $this->section);
        $ids = array_map('intval', $this->selected);
        if (! $ids) {
            $this->dispatch('toast', message: __('Select at least one thread.'), type: 'error');

            return;
        }
        $threads = Thread::withTrashed()->with('section')->where('section_id', $this->section->id)->whereIn('id', $ids)->get();
        $count = $mod->bulkThreads(auth()->user(), $threads, $action);
        $this->selected = [];
        $messages = [
            'delete' => ':count thread deleted.|:count threads deleted.',
            'restore' => ':count thread restored.|:count threads restored.',
            'lock' => ':count thread locked.|:count threads locked.',
            'unlock' => ':count thread unlocked.|:count threads unlocked.',
            'pin' => ':count thread pinned.|:count threads pinned.',
            'unpin' => ':count thread unpinned.|:count threads unpinned.',
        ];
        $this->dispatch('toast', message: trans_choice($messages[$action], $count));
    }

    public function markSectionRead(ForumService $forum): void
    {
        abort_unless(auth()->check(), 403);
        $forum->markAllRead(auth()->user(), $this->section);
        $this->dispatch('toast', message: __('All threads in :section marked as read.', ['section' => $this->section->name]));
    }

    // Refresh right away when the open thread changes
    #[On('thread-updated')]
    public function refreshList(): void
    {
    }

    public function render()
    {
        $this->authorize('view', $this->section);
        $canModerate = auth()->check() && Gate::allows('moderate', $this->section);
        $threads = $this->orderedQuery()
            ->when($canModerate && $this->showDeleted, fn ($q) => $q->withTrashed())
            ->with(['lastPoster.roles'])
            ->paginate(self::PER_PAGE, pageName: 'tp');

        // Thread links keep the list page and sort so the left bar does not jump
        $linkQuery = array_filter([
            'tp' => $threads->currentPage() > 1 ? $threads->currentPage() : null,
            'sort' => $this->sort !== 'activity' ? $this->sort : null,
        ]);

        $reads = auth()->check()
            ? DB::table('thread_reads')->where('user_id', auth()->id())->whereIn('thread_id', $threads->pluck('id'))->pluck('last_read_post_id', 'thread_id')
            : collect();

        return view('livewire.thread-list', ['threads' => $threads, 'linkQuery' => $linkQuery, 'reads' => $reads, 'sorts' => array_map(fn ($label) => __($label), self::SORTS), 'canModerate' => $canModerate]);
    }

    public function paginationView(): string
    {
        return 'partials.pager';
    }
}
