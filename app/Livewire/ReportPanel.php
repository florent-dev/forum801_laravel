<?php

namespace App\Livewire;

use App\Models\Report;
use App\Services\ModerationService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReportPanel extends Component
{
    #[Locked]
    public int $reportId;

    public function mount(Report $report): void
    {
        $this->reportId = $report->id;
        $this->authorize('resolve', $this->report());
    }

    protected function report(): Report
    {
        return Report::with(['post' => fn ($q) => $q->withTrashed(), 'post.thread.section', 'post.user', 'reporter', 'handler'])->findOrFail($this->reportId);
    }

    public function resolve(string $status, ModerationService $mod)
    {
        abort_unless(in_array($status, ['resolved', 'dismissed'], true), 422);
        $mod->resolveReport(auth()->user(), $this->report(), $status);
        session()->flash('toast', $status === 'resolved' ? __('Report resolved.') : __('Report dismissed.'));

        return $this->goToNext();
    }

    // Hide the reported post and close the report in one step
    public function hideAndResolve(ModerationService $mod)
    {
        $report = $this->report();
        if (! $report->post->is_hidden) {
            $mod->setHidden(auth()->user(), $report->post, true, $report->reason);
        }
        $mod->resolveReport(auth()->user(), $report, 'resolved');
        session()->flash('toast', __('Post hidden and report resolved.'));

        return $this->goToNext();
    }

    // Keep the post visible with a public moderation note, then close the report
    public function moderateAndResolve(ModerationService $mod)
    {
        $report = $this->report();
        if (! $report->post->moderated_at) {
            $mod->setModerated(auth()->user(), $report->post, true, $report->reason);
        }
        $mod->resolveReport(auth()->user(), $report, 'resolved');
        session()->flash('toast', __('Post moderated and report resolved.'));

        return $this->goToNext();
    }

    public function toggleLock(ModerationService $mod): void
    {
        $thread = $this->report()->post->thread;
        $mod->setLocked(auth()->user(), $thread, ! $thread->is_locked);
        $this->dispatch('thread-updated');
        $this->dispatch('toast', message: $thread->is_locked ? __('Thread locked.') : __('Thread unlocked.'));
    }

    // Continue with the next open report the moderator can handle
    protected function goToNext()
    {
        $next = Report::where('status', 'open')
            ->where('id', '!=', $this->reportId)
            ->whereHas('post', fn ($q) => $q->withTrashed()->whereHas('thread', fn ($t) => $t->withTrashed()->whereIn('section_id', ModerationQueue::moderatedSectionIds(auth()->user()))))
            ->orderBy('id')
            ->first();

        if (! $next) {
            return $this->redirect(route('moderation'), navigate: true);
        }
        $post = \App\Models\Post::withTrashed()->with('thread.section')->find($next->post_id);
        $page = \App\Http\Controllers\ForumController::pageOf(auth()->user(), $post);

        return $this->redirect(route('moderation.reports.show', ['report' => $next->id, 'page' => $page]), navigate: true);
    }

    public function render()
    {
        $report = $this->report();
        $this->authorize('resolve', $report);
        $sameCount = Report::where('post_id', $report->post_id)->where('status', 'open')->where('id', '!=', $report->id)->count();

        return view('livewire.report-panel', compact('report', 'sameCount'));
    }
}
