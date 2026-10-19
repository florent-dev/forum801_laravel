<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use App\Support\Community;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SearchService
{
    public const PERIODS = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365];

    public const SORTS = ['relevance', 'newest', 'oldest', 'replies'];

    // Filters: q, in (titles|posts|all), community, section, author, period, prefix, solved, poll, sort
    public function search(?User $viewer, array $filters): ?LengthAwarePaginator
    {
        $q = trim($filters['q'] ?? '');
        $hasFilter = $q !== '' || ! empty($filters['author']) || ! empty($filters['prefix']) || ! empty($filters['solved']) || ! empty($filters['poll']);
        if (! $hasFilter) {
            return null;
        }

        $sectionIds = $this->sectionIds($viewer, $filters);
        $authorId = ! empty($filters['author']) ? (User::where('name', $filters['author'])->value('id') ?? 0) : null;
        $since = isset(self::PERIODS[$filters['period'] ?? '']) ? now()->subDays(self::PERIODS[$filters['period']]) : null;
        $sort = in_array($filters['sort'] ?? '', self::SORTS, true) ? $filters['sort'] : 'relevance';

        return ($filters['in'] ?? 'titles') === 'posts'
            ? $this->posts($viewer, $q, $sectionIds, $authorId, $since, $filters, $sort)
            : $this->threads($q, $filters['in'] ?? 'titles', $sectionIds, $authorId, $since, $filters, $sort);
    }

    public function sectionIds(?User $viewer, array $filters): array
    {
        $community = $filters['community'] ?? Community::current();

        return Section::visibleTo($viewer)
            ->when($community !== 'all', fn ($s) => $s->inCommunity($community))
            ->when(! empty($filters['section']), fn ($s) => $s->whereKey((int) $filters['section']))
            ->pluck('id')->all();
    }

    protected function threads(string $q, string $in, array $sectionIds, ?int $authorId, $since, array $filters, string $sort): LengthAwarePaginator
    {
        $query = Thread::with(['section', 'user.roles', 'lastPoster.roles', 'poll'])
            ->whereIn('section_id', $sectionIds)
            ->when($authorId !== null, fn ($b) => $b->where('user_id', $authorId))
            ->when($since, fn ($b) => $b->where('last_post_at', '>=', $since))
            ->when(! empty($filters['prefix']), fn ($b) => $b->where('prefix', $filters['prefix']))
            ->when(! empty($filters['solved']), fn ($b) => $b->whereNotNull('solution_post_id'))
            ->when(! empty($filters['poll']), fn ($b) => $b->whereHas('poll'));

        if ($q !== '') {
            $query->where(function (Builder $b) use ($q, $in) {
                $this->match($b, $q, 'title');
                // "All" also finds threads whose visible posts match
                if ($in === 'all') {
                    $b->orWhereHas('posts', fn ($p) => $this->match($p->where('is_hidden', false)->whereNull('moderated_at'), $q, 'body'));
                }
            });
        }
        $this->order($query, $q, $sort, 'last_post_at', 'posts_count');

        return $query->paginate(25)->withQueryString();
    }

    protected function posts(?User $viewer, string $q, array $sectionIds, ?int $authorId, $since, array $filters, string $sort): LengthAwarePaginator
    {
        $query = Post::with(['thread.section', 'user.roles', 'moderator:id,name'])
            ->where('is_hidden', false)
            // Moderated text stays searchable only for the author
            ->where(fn ($b) => $b->whereNull('moderated_at')->when($viewer, fn ($b, $u) => $b->orWhere('user_id', $u->id)))
            ->whereHas('thread', fn ($t) => $t->whereIn('section_id', $sectionIds)
                ->when(! empty($filters['prefix']), fn ($t) => $t->where('prefix', $filters['prefix']))
                ->when(! empty($filters['solved']), fn ($t) => $t->whereNotNull('solution_post_id'))
                ->when(! empty($filters['poll']), fn ($t) => $t->whereHas('poll')))
            ->when($authorId !== null, fn ($b) => $b->where('user_id', $authorId))
            ->when($since, fn ($b) => $b->where('created_at', '>=', $since))
            ->when($q !== '', fn ($b) => $this->match($b, $q, 'body'));
        $this->order($query, $q, $sort, 'created_at', null);

        return $query->paginate(25)->withQueryString();
    }

    // Full-text match on PostgreSQL, LIKE elsewhere
    protected function match(Builder $query, string $q, string $column): Builder
    {
        if (DB::getDriverName() === 'pgsql') {
            return $query->whereRaw($query->getModel()->getTable().".search_vector @@ websearch_to_tsquery('simple', ?)", [$q]);
        }

        return $query->where($column, 'like', '%'.$q.'%');
    }

    protected function order(Builder $query, string $q, string $sort, string $dateColumn, ?string $countColumn): void
    {
        $table = $query->getModel()->getTable();
        match (true) {
            $sort === 'oldest' => $query->orderBy($dateColumn),
            $sort === 'replies' && $countColumn !== null => $query->orderByDesc($countColumn),
            $sort === 'relevance' && $q !== '' && DB::getDriverName() === 'pgsql' => $query->orderByRaw("ts_rank({$table}.search_vector, websearch_to_tsquery('simple', ?)) DESC", [$q]),
            default => null,
        };
        $query->orderByDesc($dateColumn);
    }
}
