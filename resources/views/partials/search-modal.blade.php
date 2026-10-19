{{-- Search dialog with advanced filters, opened by the header button or the / key --}}
@php
    $searchSections = \App\Models\Section::visibleTo(auth()->user())->orderBy('community')->orderBy('position')->get(['id', 'name', 'community']);
    $current = request()->routeIs('search') ? request()->query() : [];
    $currentCommunity = $current['community'] ?? \App\Support\Community::current();
@endphp
<div x-data="{
        show: false,
        advanced: @js(collect($current)->except(['q', 'page'])->filter()->isNotEmpty()),
        community: @js($currentCommunity),
        open() { this.show = true; this.$nextTick(() => this.$refs.q.focus()) },
     }"
     x-on:open-search.window="open()"
     x-on:keydown.window="if ($event.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) && ! document.activeElement.isContentEditable) { $event.preventDefault(); open() }"
     x-on:keydown.escape.window="show = false">
    <div class="modal-backdrop" x-show="show" x-cloak x-transition.opacity.duration.150ms>
        <form class="modal search-modal" method="GET" action="{{ route('search') }}" x-on:click.outside="show = false" role="search"
              x-on:submit="[...$el.elements].forEach(e => { if (e.name && e.type !== 'radio' && e.type !== 'checkbox' && ! e.value) e.disabled = true })">
            <div class="search-modal-input">
                <span>🔍</span>
                <input type="search" name="q" x-ref="q" value="{{ $current['q'] ?? '' }}" placeholder="{{ __('What are you looking for?') }}" aria-label="{{ __('Search') }}" autocomplete="off">
                <button type="submit" class="btn primary">{{ __('Search') }}</button>
            </div>

            <div class="search-scope">
                @foreach (['titles' => __('Thread titles'), 'posts' => __('Post contents'), 'all' => __('Titles and contents')] as $value => $label)
                    <label class="option-card"><input type="radio" name="in" value="{{ $value }}" @checked(($current['in'] ?? 'titles') === $value)> {{ $label }}</label>
                @endforeach
            </div>

            <button type="button" class="linkbtn search-toggle" x-on:click="advanced = ! advanced">
                <span x-text="advanced ? '▾' : '▸'"></span> {{ __('Advanced filters') }}
            </button>

            <div class="search-filters-grid" x-show="advanced" x-cloak>
                <label>{{ __('Community') }}
                    <select name="community" x-model="community">
                        <option value="all">{{ __('All communities') }}</option>
                        @foreach (\App\Support\Community::all() as $code => $c)<option value="{{ $code }}">{{ $c['label'] }}</option>@endforeach
                    </select>
                </label>
                <label>{{ __('Section') }}
                    <select name="section">
                        <option value="">{{ __('All sections') }}</option>
                        @foreach ($searchSections as $s)
                            <option value="{{ $s->id }}" data-community="{{ $s->community }}" x-show="community === 'all' || community === @js($s->community)" @selected((int) ($current['section'] ?? 0) === $s->id)>{{ $s->name }} ({{ strtoupper($s->community) }})</option>
                        @endforeach
                    </select>
                </label>
                <label>{{ __('Author') }}
                    <input type="text" name="author" value="{{ $current['author'] ?? '' }}" placeholder="{{ __('Nickname') }}" data-mention-input autocomplete="off">
                </label>
                <label>{{ __('Date') }}
                    <select name="period">
                        <option value="">{{ __('Any time') }}</option>
                        @foreach (['day' => __('Last 24 hours'), 'week' => __('Last week'), 'month' => __('Last month'), 'year' => __('Last year')] as $value => $label)
                            <option value="{{ $value }}" @selected(($current['period'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>{{ __('Prefix') }}
                    <select name="prefix">
                        <option value="">{{ __('Any') }}</option>
                        @foreach (\App\Models\Thread::PREFIXES as $key => $label)<option value="{{ $key }}" @selected(($current['prefix'] ?? '') === $key)>{{ __($label) }}</option>@endforeach
                    </select>
                </label>
                <label>{{ __('Sort by') }}
                    <select name="sort">
                        @foreach (['relevance' => __('Relevance'), 'newest' => __('Newest first'), 'oldest' => __('Oldest first'), 'replies' => __('Most replies')] as $value => $label)
                            <option value="{{ $value }}" @selected(($current['sort'] ?? 'relevance') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="search-checks">
                    <label class="switch"><input type="checkbox" name="solved" value="1" @checked(! empty($current['solved']))><span class="slider"></span><span class="switch-label">✅ {{ __('Solved only') }}</span></label>
                    <label class="switch"><input type="checkbox" name="poll" value="1" @checked(! empty($current['poll']))><span class="slider"></span><span class="switch-label">📊 {{ __('With a poll') }}</span></label>
                </div>
            </div>

            <div class="search-tips">
                {{ __('Tips: use "quotes" for an exact phrase, -word to exclude a word, or OR between alternatives.') }}
                <span class="spacer"></span><kbd>Esc</kbd> {{ __('to close') }}
            </div>
        </form>
    </div>
</div>
