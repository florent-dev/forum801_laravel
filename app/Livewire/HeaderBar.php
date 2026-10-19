<?php

namespace App\Livewire;

use App\Models\Report;
use App\Models\Section;
use App\Models\Thread;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class HeaderBar extends Component
{
    public function markAllNotificationsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $user = auth()->user();
        $unread = Thread::query()
            ->join('thread_reads', fn ($j) => $j->on('thread_reads.thread_id', '=', 'threads.id')->where('thread_reads.user_id', $user->id))
            ->whereColumn('threads.last_post_id', '>', 'thread_reads.last_read_post_id')
            ->whereIn('threads.section_id', Section::visibleTo($user)->select('id'))
            ->count();

        $reports = null;
        if (Gate::allows('access-moderation')) {
            $reports = Report::where('status', 'open')
                ->whereHas('post.thread', fn ($q) => $q->whereIn('section_id', ModerationQueue::moderatedSectionIds($user)))
                ->count();
        }

        return view('livewire.header-bar', [
            'user' => $user,
            'unread' => $unread,
            'reports' => $reports,
            'messages' => $user->unreadConversationsCount(),
            'notificationCount' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->limit(8)->get(),
        ]);
    }
}
