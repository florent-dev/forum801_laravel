<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Section;
use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ForumController extends Controller
{
    public function home()
    {
        return view('forum.home');
    }

    public function section(Section $section)
    {
        Gate::authorize('view', $section);

        return view('forum.section', compact('section'));
    }

    public function thread(Request $request, Thread $thread, ?string $slug = null)
    {
        Gate::authorize('view', $thread);
        if ($slug !== $thread->slug) {
            return redirect()->route('threads.show', ['thread' => $thread->id, 'slug' => $thread->slug] + $request->only('page', 'tp', 'sort'), 301);
        }

        // Count one view per thread per session
        $viewed = $request->session()->get('viewed_threads', []);
        if (! in_array($thread->id, $viewed, true)) {
            $thread->increment('views_count', 1, ['updated_at' => $thread->updated_at]);
            $request->session()->put('viewed_threads', array_slice([...$viewed, $thread->id], -200));
        }

        // Resume where the member stopped reading when no page is asked
        if ($request->user() && ! $request->has('page')) {
            $page = $this->resumePage($request->user(), $thread);
            if ($page > 1) {
                return redirect()->route('threads.show', ['thread' => $thread->id, 'slug' => $thread->slug, 'page' => $page] + $request->only('tp', 'sort'));
            }
        }

        return view('forum.thread', ['thread' => $thread->load('section')]);
    }

    // Page holding the first unread post, or the last read one when all is read
    protected function resumePage(\App\Models\User $user, Thread $thread): int
    {
        $lastRead = DB::table('thread_reads')->where(['user_id' => $user->id, 'thread_id' => $thread->id])->value('last_read_post_id');
        if (! $lastRead) {
            return 1;
        }
        $target = Post::where('thread_id', $thread->id)->where('id', '>', $lastRead)->where('is_hidden', false)->orderBy('id')->first()
            ?? Post::withTrashed()->find($lastRead);

        return $target ? static::pageOf($user, $target) : 1;
    }

    // Redirect to the page of the thread that contains the post
    public function post(Request $request, Post $post)
    {
        $thread = $post->thread;
        Gate::authorize('view', $post);

        // The p parameter marks the post the visitor came for
        return redirect()->to(route('threads.show', ['thread' => $thread->id, 'slug' => $thread->slug, 'page' => static::pageOf($request->user(), $post), 'p' => $post->id]));
    }

    public static function pageOf(?\App\Models\User $user, Post $post): int
    {
        $query = Post::where('thread_id', $post->thread_id)->where('id', '<=', $post->id)->when($post->trashed(), fn ($q) => $q->withTrashed());
        if (! $user || ! Gate::forUser($user)->allows('moderate', $post->thread->section)) {
            $query->where(fn ($q) => $q->where('is_hidden', false)->when($user, fn ($q) => $q->orWhere('user_id', $user->id)));
        }

        return max(1, (int) ceil($query->count() / Post::PER_PAGE));
    }

    // Jump to the first post the user has not read yet
    public function firstUnread(Request $request, Thread $thread)
    {
        Gate::authorize('view', $thread);
        $lastRead = $request->user()
            ? (int) DB::table('thread_reads')->where(['user_id' => $request->user()->id, 'thread_id' => $thread->id])->value('last_read_post_id')
            : 0;
        $post = Post::where('thread_id', $thread->id)->where('id', '>', $lastRead)->where('is_hidden', false)->orderBy('id')->first()
            ?? Post::where('thread_id', $thread->id)->where('is_hidden', false)->orderByDesc('id')->first();

        return $post ? $this->post($request, $post) : redirect()->to($thread->url());
    }

    public function online()
    {
        $users = \App\Models\User::with('roles')->visiblyOnline()->orderBy('name')->get();

        return view('forum.online', compact('users'));
    }

    public function notifications(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(30);

        return view('forum.notifications', compact('notifications'));
    }

    public function openNotification(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? route('home');

        // Only follow links that stay on this site
        $sameHost = parse_url($url, PHP_URL_HOST) === $request->getHost() && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);

        return redirect()->to($sameHost ? $url : route('home'));
    }

    public function subscriptions(Request $request)
    {
        $threads = $request->user()->subscriptions()
            ->with(['section', 'lastPoster.roles'])
            ->whereIn('section_id', Section::visibleTo($request->user())->select('id'))
            ->orderByDesc('last_post_at')
            ->paginate(30);

        return view('forum.subscriptions', compact('threads'));
    }

    public function bookmarks(Request $request)
    {
        $posts = $request->user()->bookmarks()
            ->with(['thread.section', 'user.roles'])
            ->whereHas('thread', fn ($t) => $t->whereIn('section_id', Section::visibleTo($request->user())->select('id')))
            ->where('is_hidden', false)
            ->orderByDesc('bookmarks.created_at')
            ->paginate(30);

        return view('forum.bookmarks', compact('posts'));
    }

    public function messages()
    {
        return view('messages.index');
    }

    public function conversation(Request $request, \App\Models\Conversation $conversation)
    {
        Gate::authorize('view', $conversation);

        return view('messages.show', compact('conversation'));
    }

    public function createThread(Section $section)
    {
        Gate::authorize('createThread', $section);

        return view('forum.new-thread', compact('section'));
    }

    public function sectionSettings(Section $section)
    {
        Gate::authorize('update', $section);

        return view('forum.section-settings', compact('section'));
    }

    public function unread(Request $request)
    {
        $user = $request->user();
        $threads = Thread::with(['section', 'lastPoster.roles'])
            ->join('thread_reads', fn ($j) => $j->on('thread_reads.thread_id', '=', 'threads.id')->where('thread_reads.user_id', $user->id))
            ->whereColumn('threads.last_post_id', '>', 'thread_reads.last_read_post_id')
            ->whereIn('threads.section_id', Section::visibleTo($user)->select('id'))
            ->orderByDesc('threads.last_post_at')
            ->select('threads.*')
            ->paginate(30);

        return view('forum.unread', compact('threads'));
    }

    public function moderation()
    {
        return view('moderation.index');
    }

    // Review a report next to the reported post inside its thread
    public function report(Request $request, \App\Models\Report $report)
    {
        Gate::authorize('resolve', $report);
        $post = Post::withTrashed()->with('thread.section')->findOrFail($report->post_id);
        if (! $request->has('page')) {
            return redirect()->to(route('moderation.reports.show', ['report' => $report->id, 'page' => static::pageOf($request->user(), $post)]));
        }

        return view('moderation.report', ['report' => $report, 'post' => $post, 'thread' => $post->thread]);
    }

    public function search(Request $request, \App\Services\SearchService $search)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'in' => ['nullable', 'in:titles,posts,all'],
            'section' => ['nullable', 'integer'],
            'author' => ['nullable', 'string', 'max:30'],
            'community' => ['nullable', 'string', 'max:5'],
            'period' => ['nullable', 'in:'.implode(',', array_keys(\App\Services\SearchService::PERIODS))],
            'prefix' => ['nullable', 'in:'.implode(',', array_keys(Thread::PREFIXES))],
            'solved' => ['nullable', 'boolean'],
            'poll' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:'.implode(',', \App\Services\SearchService::SORTS)],
        ]);
        // Search the current community unless another one, or all, is asked for
        $filters['community'] = ($filters['community'] ?? null) === 'all' ? 'all'
            : (\App\Support\Community::exists($filters['community'] ?? null) ? $filters['community'] : \App\Support\Community::current());
        $filters['in'] ??= 'titles';
        $filters['q'] = trim($filters['q'] ?? '');

        return view('forum.search', [
            'filters' => $filters,
            'results' => $search->search($request->user(), $filters),
            'q' => $filters['q'],
            'in' => $filters['in'],
        ]);
    }
}
