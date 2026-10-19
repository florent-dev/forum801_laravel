<?php

namespace App\Livewire;

use App\Models\Report;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ReportList extends Component
{
    use WithPagination;

    public const STATUSES = ['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'];

    #[Url(as: 'status', except: 'open')]
    public string $status = 'open';

    public ?int $activeReportId = null;

    public function mount(?int $activeReportId = null): void
    {
        $this->authorize('access-moderation');
        $this->activeReportId = $activeReportId;
    }

    public function updatedStatus(): void
    {
        if (! array_key_exists($this->status, self::STATUSES)) {
            $this->status = 'open';
        }
        $this->resetPage('rp');
    }

    public function render()
    {
        $this->authorize('access-moderation');
        $sectionIds = ModerationQueue::moderatedSectionIds(auth()->user());
        $reports = Report::with(['post' => fn ($q) => $q->withTrashed(), 'post.user.roles', 'post.thread.section', 'reporter.roles'])
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->whereHas('post', fn ($q) => $q->withTrashed()->whereHas('thread', fn ($t) => $t->withTrashed()->whereIn('section_id', $sectionIds)))
            ->latest('id')
            ->paginate(30, pageName: 'rp');

        return view('livewire.report-list', [
            'reports' => $reports,
            'statuses' => array_map(fn ($label) => __($label), self::STATUSES),
        ]);
    }

    public function paginationView(): string
    {
        return 'partials.pager';
    }
}
