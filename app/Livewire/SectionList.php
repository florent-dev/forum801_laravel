<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use App\Support\Community;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class SectionList extends Component
{
    public function render()
    {
        $community = Community::current();
        $sections = Section::visibleTo(auth()->user())
            ->forum()
            ->inCommunity($community)
            ->withCount('threads')
            ->withSum('threads as posts_total', 'posts_count')
            ->with('latestThread.lastPoster.roles')
            ->orderBy('position')
            ->get();

        // Global counters are cheap to cache for a minute
        $stats = Cache::remember('forum-stats', 60, fn () => [
            'members' => User::count(),
            'threads' => Thread::count(),
            'posts' => Post::where('is_hidden', false)->count(),
            'newest' => User::latest('id')->first(['id', 'name']),
        ]);
        $online = User::with('roles')->visiblyOnline()->orderByDesc('last_seen_at')->limit(30)->get();

        // Guild forums of the user, with what makes them useful at a glance
        $memberships = auth()->user()?->guildMemberships()->with(['guild' => fn ($q) => $q->withCount([
            'members',
            'applications as pending_count' => fn ($a) => $a->where('status', 'pending'),
        ])->with(['section' => fn ($s) => $s->withCount('threads')->withSum('threads as posts_total', 'posts_count')->with('latestThread.lastPoster.roles')])])->get() ?? collect();
        $guilds = $memberships->filter(fn ($m) => $m->guild)->map(function ($m) {
            $m->guild->setAttribute('my_role', $m->role);

            return $m->guild;
        })->values();

        return view('livewire.section-list', compact('sections', 'stats', 'online', 'community', 'guilds'));
    }
}
