<div class="settings-page">
    <div class="settings-card">
        <h3>🔎 {{ __('Search posts') }}</h3>
        <p class="muted" style="font-size:12px;margin:4px 0 8px">{{ __('Every post in the sections you moderate, including hidden and deleted ones.') }}</p>
        <div class="mod-search">
            <input type="text" wire:model.live.debounce.400ms="user" placeholder="{{ __('Exact nickname') }}" aria-label="{{ __('Author') }}">
            @can('view-ips')<input type="text" wire:model.live.debounce.400ms="ip" placeholder="{{ __('IP or beginning of an IP') }}" aria-label="IP">@endcan
            <input type="text" wire:model.live.debounce.400ms="text" placeholder="{{ __('Text in the post') }}" aria-label="{{ __('Text in the post') }}" maxlength="200">
            <select wire:model.live="postStatus" aria-label="{{ __('Status') }}">
                <option value="all">{{ __('All posts') }}</option>
                <option value="visible">{{ __('Visible') }}</option>
                <option value="hidden">{{ __('Hidden') }}</option>
                <option value="moderated">{{ __('Moderated') }}</option>
                <option value="deleted">{{ __('Deleted') }}</option>
            </select>
            <select wire:model.live="section" aria-label="{{ __('Section') }}">
                <option value="">{{ __('All my sections') }}</option>
                @foreach ($sections as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
            @if ($searching)<button type="button" class="linkbtn" wire:click="resetSearch">{{ __('Clear') }}</button>@endif
        </div>
    </div>

    @if (! $searching)
        <div class="empty">{{ __('Fill in at least one filter.') }}</div>
    @else
        <div class="settings-card">
            <h3>{{ trans_choice(':count result|:count results', $results->total(), ['count' => number_format($results->total(), 0, '', ' ')]) }}</h3>
            @forelse ($results as $post)
                <div class="mod-result {{ $post->trashed() ? 'deleted' : '' }}" wire:key="mr{{ $post->id }}">
                    <div class="meta">
                        @if ($post->user)<a href="{{ route('moderation.users.show', $post->user) }}" wire:navigate style="color: {{ $post->user->displayColor() }}" title="{{ __('User file') }}">{{ $post->user->name }}</a>@else<span class="muted">{{ __('deleted') }}</span>@endif
                        · <a href="{{ route('posts.show', $post) }}" wire:navigate>{{ $post->thread?->title }}</a>
                        <span class="muted">· {{ $post->thread?->section?->name }} · <span title="{{ $post->created_at->isoFormat('LLL') }}">{{ $post->created_at->shortDateTime() }}</span></span>
                        @can('view-ips')@if ($post->ip)<button type="button" class="linkbtn" wire:click="\$set('ip', @js($post->ip))" title="{{ __('Posts from this IP') }}"><code>{{ $post->ip }}</code></button>@endif @endcan
                        @if ($post->trashed())<span class="tag-badge danger">{{ __('Deleted') }}</span>@endif
                        @if ($post->is_hidden)<span class="tag-badge">{{ __('Hidden') }}</span>@endif
                        @if ($post->moderated_at)<span class="tag-badge">{{ __('Moderated') }}</span>@endif
                    </div>
                    <div class="excerpt">{{ $post->excerpt(260) }}</div>
                </div>
            @empty
                <div class="empty">{{ __('No posts match.') }}</div>
            @endforelse
        </div>
    @endif
</div>
