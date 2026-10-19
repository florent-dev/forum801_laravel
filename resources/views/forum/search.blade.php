@php
    $labels = [
        'in' => ['titles' => __('Thread titles'), 'posts' => __('Post contents'), 'all' => __('Titles and contents')],
        'period' => ['day' => __('Last 24 hours'), 'week' => __('Last week'), 'month' => __('Last month'), 'year' => __('Last year')],
        'sort' => ['newest' => __('Newest first'), 'oldest' => __('Oldest first'), 'replies' => __('Most replies')],
    ];
    $chips = collect([
        'in' => $labels['in'][$filters['in']] ?? null,
        'community' => $filters['community'] === 'all' ? __('All communities') : \App\Support\Community::label($filters['community']),
        'section' => ! empty($filters['section']) ? \App\Models\Section::find($filters['section'])?->name : null,
        'author' => ! empty($filters['author']) ? '👤 '.$filters['author'] : null,
        'period' => $labels['period'][$filters['period'] ?? ''] ?? null,
        'prefix' => ! empty($filters['prefix']) ? __(\App\Models\Thread::PREFIXES[$filters['prefix']]) : null,
        'solved' => ! empty($filters['solved']) ? '✅ '.__('Solved only') : null,
        'poll' => ! empty($filters['poll']) ? '📊 '.__('With a poll') : null,
        'sort' => $labels['sort'][$filters['sort'] ?? ''] ?? null,
    ])->filter();
    $removable = ['section', 'author', 'period', 'prefix', 'solved', 'poll', 'sort'];
@endphp
<x-layouts.app :title="__('Search')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <div class="panel-head">
        <h1 class="with-art"><x-art name="search" /> {{ $q !== '' ? __('Results for “:q”', ['q' => $q]) : __('Search') }}
            @if ($results)<span class="muted">· {{ trans_choice(':count result|:count results', $results->total()) }}</span>@endif
        </h1>
        @if ($results)@include('partials.pager-links', ['paginator' => $results])@endif
    </div>
    <div class="scroll">
        <div class="search-summary">
            @foreach ($chips as $key => $label)
                @if (in_array($key, $removable, true))
                    <a class="chip filter-chip" href="{{ route('search', collect(request()->query())->except([$key, 'page'])->all()) }}" title="{{ __('Remove this filter') }}">{{ $label }} ✕</a>
                @else
                    <span class="chip">{{ $label }}</span>
                @endif
            @endforeach
            <button type="button" class="btn small" x-data x-on:click="$dispatch('open-search')">⚙️ {{ __('Edit filters') }}</button>
        </div>

        @if (! $results)
            <div class="search-empty">
                <div class="big">🔍</div>
                <p>{{ __('Search thread titles, post contents, or both. Open the filters to narrow down by community, section, author, date, prefix and more.') }}</p>
                <button type="button" class="btn primary" x-data x-on:click="$dispatch('open-search')">{{ __('Open the search') }}</button>
            </div>
        @elseif ($results->isEmpty())
            <div class="search-empty">
                <div class="big">🧀</div>
                <p>{{ __('No results. Try other words or remove some filters.') }}</p>
            </div>
        @elseif ($in === 'posts')
            @foreach ($results as $post)
                <a class="activity-card" href="{{ route('posts.show', $post) }}" wire:navigate>
                    <div class="activity-head">
                        <span class="title">@if ($post->thread->prefix)<span class="prefix prefix-{{ $post->thread->prefix }}">{{ __(\App\Models\Thread::PREFIXES[$post->thread->prefix] ?? '') }}</span>@endif{{ $post->thread->title }}</span>
                        <span class="muted">{{ $post->thread->section->name }}</span>
                    </div>
                    <div class="activity-body">{{ $post->excerpt(260) }}</div>
                    <div class="meta" style="margin-top:6px"><span style="color: {{ $post->user?->displayColor() }}">{{ $post->user?->name }}</span><span>· {{ $post->created_at->diffForHumans() }}</span></div>
                </a>
            @endforeach
        @else
            @foreach ($results as $thread)
                <a class="activity-card" href="{{ $thread->url() }}" wire:navigate>
                    <div class="activity-head">
                        <span class="title">
                            @if ($thread->solution_post_id)<span class="prefix solved">✅</span>@endif
                            @if ($thread->prefix)<span class="prefix prefix-{{ $thread->prefix }}">{{ __(\App\Models\Thread::PREFIXES[$thread->prefix] ?? '') }}</span>@endif
                            @if ($thread->poll)📊 @endif{{ $thread->title }}
                        </span>
                        <span class="muted">{{ $thread->section->name }}</span>
                    </div>
                    <div class="meta">
                        <span class="count">{{ trans_choice(':count post|:count posts', $thread->posts_count) }}</span>
                        <span>{{ __('by') }} <span style="color: {{ $thread->user?->displayColor() }}">{{ $thread->user?->name }}</span></span>
                        <span>· {{ __('last post :when', ['when' => $thread->last_post_at?->diffForHumans()]) }}</span>
                    </div>
                </a>
            @endforeach
        @endif
    </div>
</x-layouts.app>
