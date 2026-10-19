<?php

namespace App\Livewire;

use App\Models\ModerationLog;
use App\Models\Report;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use App\Services\ModerationService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ModerationQueue extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'deleted';

    public array $selected = [];

    // Search tab filters
    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $ip = '';

    #[Url(as: 'state', except: 'all')]
    public string $postStatus = 'all';

    #[Url(except: '')]
    public string $section = '';

    #[Url(except: '')]
    public string $text = '';

    // Stats tab period, in days
    #[Url(except: 30)]
    public int $days = 30;

    public function updated(string $property): void
    {
        if (in_array($property, ['user', 'ip', 'postStatus', 'section', 'text', 'days'], true)) {
            $this->resetPage();
        }
    }

    public function resetSearch(): void
    {
        $this->reset('user', 'ip', 'postStatus', 'section', 'text');
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('access-moderation');
    }

    // Ids of the sections the user is allowed to moderate
    public static function moderatedSectionIds(User $user): array
    {
        return Section::all()->filter(fn (Section $s) => Gate::forUser($user)->allows('moderate', $s))->pluck('id')->all();
    }

    public function restoreThreads(array $ids, ModerationService $mod): void
    {
        $threads = Thread::onlyTrashed()->with('section')->whereIn('id', array_map('intval', $ids))->get();
        $count = $mod->bulkThreads(auth()->user(), $threads, 'restore');
        $this->selected = [];
        $this->dispatch('toast', message: trans_choice(':count thread restored.|:count threads restored.', $count));
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['deleted', 'logs', 'search', 'stats'], true) ? $tab : 'deleted';
        $this->selected = [];
        $this->resetPage();
    }

    public function render()
    {
        $sectionIds = static::moderatedSectionIds(auth()->user());

        $deleted = $this->tab === 'deleted'
            ? Thread::onlyTrashed()->with(['section', 'user.roles', 'deleter'])
                ->whereIn('section_id', $sectionIds)
                ->latest('deleted_at')
                ->paginate(25)
            : null;

        $logs = $this->tab === 'logs'
            ? ModerationLog::with(['moderator.roles', 'section'])
                ->where(fn ($q) => $q->whereIn('section_id', $sectionIds)->when(Gate::allows('admin'), fn ($q) => $q->orWhereNull('section_id')))
                ->latest('id')
                ->paginate(30)
            : null;

        $insights = app(\App\Services\ModerationInsights::class);
        $searching = $this->tab === 'search' && (trim($this->user.$this->ip.$this->text) !== '' || $this->postStatus !== 'all' || $this->section !== '');
        $results = $searching ? $insights->search(auth()->user(), [
            'user' => $this->user, 'ip' => $this->ip, 'status' => in_array($this->postStatus, \App\Services\ModerationInsights::STATUSES, true) ? $this->postStatus : 'all',
            'section' => $this->section, 'text' => mb_substr($this->text, 0, 200),
        ])->paginate(25) : null;
        $stats = $this->tab === 'stats' ? $insights->stats(auth()->user(), in_array($this->days, [7, 30, 90, 365], true) ? $this->days : 30) : null;
        $sections = $this->tab === 'search' ? Section::whereIn('id', $sectionIds)->orderBy('name')->get(['id', 'name']) : collect();

        return view('livewire.moderation-queue', compact('logs', 'deleted', 'results', 'stats', 'sections', 'searching'));
    }

    public function paginationView(): string
    {
        return 'partials.pager';
    }
}
