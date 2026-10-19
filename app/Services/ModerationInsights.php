<?php

namespace App\Services;

use App\Livewire\ModerationQueue;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Report;
use App\Models\Sanction;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

// Moderator user file, moderation search and statistics; always limited to the sections the moderator handles
class ModerationInsights
{
    public const STATUSES = ['all', 'visible', 'hidden', 'moderated', 'deleted'];

    public function sectionIds(User $mod): array
    {
        return ModerationQueue::moderatedSectionIds($mod);
    }

    // Reports and post counts about one user, seen from one moderator's sections
    public function userSummary(User $mod, User $user): array
    {
        $sections = $this->sectionIds($mod);
        $posts = Post::withTrashed()->where('user_id', $user->id)->whereHas('thread', fn ($t) => $t->withTrashed()->whereIn('section_id', $sections));
        $received = Report::whereIn('post_id', (clone $posts)->select('posts.id'));

        return [
            'posts' => [
                'total' => (clone $posts)->count(),
                'hidden' => (clone $posts)->where('is_hidden', true)->count(),
                'moderated' => (clone $posts)->whereNotNull('moderated_at')->count(),
                'deleted' => (clone $posts)->whereNotNull('posts.deleted_at')->count(),
            ],
            'received' => (clone $received)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            'receivedLatest' => (clone $received)->with(['post' => fn ($q) => $q->withTrashed(), 'post.thread'])->latest('id')->limit(10)->get(),
            'sent' => Report::where('reporter_id', $user->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
        ];
    }

    // Posts in the moderator's sections, deleted ones included, filtered by author, IP, status, section and text
    public function search(User $mod, array $f): Builder
    {
        $sections = $this->sectionIds($mod);
        if (! empty($f['section']) && in_array((int) $f['section'], $sections, true)) {
            $sections = [(int) $f['section']];
        }

        return Post::withTrashed()
            ->with(['thread' => fn ($q) => $q->withTrashed()->with('section'), 'user.roles'])
            ->whereHas('thread', fn ($t) => $t->withTrashed()->whereIn('section_id', $sections))
            ->when($f['user'] ?? null, fn ($q, $name) => $q->whereIn('user_id', User::whereRaw('lower(name) = lower(?)', [trim($name)])->select('id')))
            ->when(($f['ip'] ?? null) && Gate::forUser($mod)->allows('view-ips'), fn ($q) => $q->where('ip', 'like', $this->like($f['ip'], true)))
            ->when($f['text'] ?? null, fn ($q, $text) => $q->where('body', 'ilike', $this->like($text, true, true)))
            ->when(($f['status'] ?? 'all') !== 'all', fn ($q) => match ($f['status']) {
                'visible' => $q->whereNull('posts.deleted_at')->where('is_hidden', false)->whereNull('moderated_at'),
                'hidden' => $q->where('is_hidden', true),
                'moderated' => $q->whereNotNull('moderated_at'),
                'deleted' => $q->whereNotNull('posts.deleted_at'),
                default => $q,
            })
            ->latest('posts.id');
    }

    public function stats(User $mod, int $days): array
    {
        $sections = $this->sectionIds($mod);
        $since = now()->subDays($days);
        $reports = Report::query()->where('reports.created_at', '>=', $since)
            ->whereIn('post_id', Post::withTrashed()->whereHas('thread', fn ($t) => $t->withTrashed()->whereIn('section_id', $sections))->select('id'));
        $handled = (clone $reports)->whereNotNull('handled_at');
        $seconds = 'extract(epoch from (handled_at - reports.created_at))';

        $byModerator = (clone $handled)->selectRaw("handled_by, count(*) as handled, sum(case when status = 'resolved' then 1 else 0 end) as resolved, sum(case when status = 'dismissed' then 1 else 0 end) as dismissed, avg($seconds) as avg_seconds")
            ->groupBy('handled_by')->orderByDesc('handled')->get();
        $actions = ModerationLog::where('created_at', '>=', $since)
            ->where(fn ($q) => $q->whereIn('section_id', $sections)->when(Gate::forUser($mod)->allows('admin'), fn ($q) => $q->orWhereNull('section_id')))
            ->where('action', 'not like', 'report.%');
        $actionsByMod = (clone $actions)->selectRaw('moderator_id, count(*) as n')->groupBy('moderator_id')->pluck('n', 'moderator_id');
        $moderators = User::with('roles')->whereIn('id', $byModerator->pluck('handled_by')->merge($actionsByMod->keys())->filter()->unique())->get()->keyBy('id');

        $perModerator = $moderators->map(fn (User $u) => [
            'user' => $u,
            'handled' => (int) ($byModerator->firstWhere('handled_by', $u->id)?->handled ?? 0),
            'resolved' => (int) ($byModerator->firstWhere('handled_by', $u->id)?->resolved ?? 0),
            'dismissed' => (int) ($byModerator->firstWhere('handled_by', $u->id)?->dismissed ?? 0),
            'avg' => $byModerator->firstWhere('handled_by', $u->id)?->avg_seconds,
            'actions' => (int) ($actionsByMod[$u->id] ?? 0),
        ])->sortByDesc(fn ($r) => $r['handled'] + $r['actions'])->values();

        $topSections = (clone $reports)->join('posts', 'posts.id', '=', 'reports.post_id')->join('threads', 'threads.id', '=', 'posts.thread_id')
            ->selectRaw('threads.section_id, count(*) as n')->groupBy('threads.section_id')->orderByDesc('n')->limit(8)->pluck('n', 'section_id');
        $topUsers = (clone $reports)->join('posts', 'posts.id', '=', 'reports.post_id')->whereNotNull('posts.user_id')
            ->selectRaw('posts.user_id, count(*) as n, count(distinct reports.post_id) as posts')->groupBy('posts.user_id')->orderByDesc('n')->limit(10)->get();
        $users = User::with('roles')->whereIn('id', $topUsers->pluck('user_id'))->get()->keyBy('id');

        return [
            'received' => (clone $reports)->count(),
            'open' => Report::where('status', 'open')->whereIn('post_id', Post::withTrashed()->whereHas('thread', fn ($t) => $t->withTrashed()->whereIn('section_id', $sections))->select('id'))->count(),
            'handled' => (clone $handled)->count(),
            'avgSeconds' => (clone $handled)->selectRaw("avg($seconds) as s")->value('s'),
            'medianSeconds' => (clone $handled)->selectRaw("percentile_cont(0.5) within group (order by $seconds) as s")->value('s'),
            'perModerator' => $perModerator,
            'topSections' => Section::whereIn('id', $topSections->keys())->get()->keyBy('id')->map(fn ($s) => ['section' => $s, 'n' => $topSections[$s->id]])->sortByDesc('n')->values(),
            'topUsers' => $topUsers->map(fn ($r) => ['user' => $users[$r->user_id] ?? null, 'n' => (int) $r->n, 'posts' => (int) $r->posts])->filter(fn ($r) => $r['user'])->values(),
            'actions' => (clone $actions)->selectRaw('action, count(*) as n')->groupBy('action')->orderByDesc('n')->pluck('n', 'action'),
            'sanctions' => Sanction::where('created_at', '>=', $since)->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type'),
        ];
    }

    // "1 h 20 min", "3 days"
    public static function duration(?float $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        return \Carbon\CarbonInterval::seconds((int) round($seconds))->cascade()->forHumans(['parts' => 2, 'short' => true]);
    }

    protected function like(string $value, bool $prefixOnly, bool $anywhere = false): string
    {
        $escaped = addcslashes(trim($value), '%_\\');

        return $anywhere ? '%'.$escaped.'%' : ($prefixOnly ? $escaped.'%' : '%'.$escaped.'%');
    }
}
