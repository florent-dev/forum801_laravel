<div style="display:flex;flex-direction:column;min-height:0;flex:1" wire:poll.20s>
    <div class="panel-head">
        {{-- Pages on the left, sorting on the right --}}
        {{ $threads->links() }}
        <select class="select-sm" wire:model.live="sort" aria-label="{{ __('Sort threads') }}">
            @foreach ($sorts as $key => $label)<option value="{{ $key }}">{{ __($label) }}</option>@endforeach
        </select>
    </div>
    <a class="section-head" href="{{ route('home') }}" wire:navigate title="{{ __('Back to the sections list') }}">
        <span class="back">&lsaquo;</span>
        <img class="icon" src="{{ $section->iconUrl() }}" alt="">
        <div>
            <div class="name">{{ $section->name }}</div>
            <img class="flag" src="{{ $section->flagUrl() }}" alt="{{ $section->community }}" title="{{ $section->communityLabel() }}">
            @if ($section->isPrivate())<img src="{{ asset('img/locked.png') }}" class="lock-icon" alt="{{ __('private') }}" title="{{ __('Private') }}">@endif
            @if ($section->is_archived)<span title="{{ __('Archived: read-only') }}">🗄️</span>@endif
        </div>
    </a>
    <div class="list-tools">
        @auth
            <span style="display:flex;gap:2px;margin-left:auto">
                @if ($canModerate)
                    <button type="button" class="icon-btn {{ $selectMode ? 'on' : '' }}" wire:click="toggleSelectMode" title="{{ __('Select threads for bulk actions') }}">☑️</button>
                    <button type="button" class="icon-btn {{ $showDeleted ? 'on' : '' }}" wire:click="toggleShowDeleted" title="{{ $showDeleted ? __('Hide deleted threads') : __('Show deleted threads') }}">👻</button>
                @endif
                <button type="button" class="icon-btn" wire:click="markSectionRead" title="{{ __('Mark every thread in this section as read') }}">✓ {{ __('Read') }}</button>
            </span>
        @endauth
    </div>
    @if ($selectMode)
        <div class="bulk-bar" style="position:static">
            <span>{!! __(':count selected', ['count' => '<strong>'.count($selected).'</strong>']) !!}</span>
            <button type="button" class="btn small danger" x-on:click="$confirm(@js(__('Delete the selected threads? They can be restored later.')), () => $wire.bulk('delete'), { title: @js(__('Delete threads')), label: @js(__('Delete')) })">{{ __('Delete') }}</button>
            <button type="button" class="btn small primary" wire:click="bulk('restore')">{{ __('Restore') }}</button>
            <button type="button" class="btn small" wire:click="bulk('lock')">{{ __('Lock') }}</button>
            <button type="button" class="btn small" wire:click="bulk('unlock')">{{ __('Unlock') }}</button>
            <button type="button" class="btn small" wire:click="bulk('pin')">{{ __('Pin') }}</button>
            <button type="button" class="btn small" wire:click="bulk('unpin')">{{ __('Unpin') }}</button>
            <button type="button" class="linkbtn" x-on:click="$wire.set('selected', [...document.querySelectorAll('.thread-check')].map(c => c.value))">{{ __('All') }}</button>
            <button type="button" class="linkbtn" wire:click="$set('selected', [])">{{ __('None') }}</button>
        </div>
    @endif
    <div class="scroll" data-section="{{ $section->id }}-{{ $threads->currentPage() }}-{{ $sort }}">
        @forelse ($threads as $thread)
            @php($lastRead = $reads[$thread->id] ?? null)
            @php($read = auth()->check() && $lastRead !== null && $lastRead >= $thread->last_post_id)
            @php($hasNew = auth()->check() && $lastRead !== null && $lastRead < $thread->last_post_id)
            @php($checked = in_array((string) $thread->id, array_map('strval', $selected), true))
            @if ($selectMode)
            <label wire:key="t{{ $thread->id }}" class="ui-content selectable {{ $thread->is_pinned ? 'sticky' : '' }} {{ $thread->trashed() ? 'deleted' : '' }} {{ $checked ? 'checked' : '' }}">
                <input type="checkbox" class="thread-check" value="{{ $thread->id }}" wire:model.live="selected">
                <div>
            @else
            <a wire:key="t{{ $thread->id }}" href="{{ route('threads.show', ['thread' => $thread->id, 'slug' => $thread->slug] + $linkQuery) }}" wire:navigate
               class="ui-content {{ $thread->is_pinned ? 'sticky' : '' }} {{ $thread->id === $activeThreadId ? 'selected' : '' }} {{ $read ? 'read' : '' }} {{ $thread->trashed() ? 'deleted' : '' }}">
            @endif
                <div class="title tags">
                    @if ($hasNew)<span class="unread-dot" title="{{ __('New posts since your last visit') }}"></span>@endif
                    @if ($thread->is_pinned)<span title="{{ __('Pinned') }}">📌</span>@endif
                    @if ($thread->is_locked)<img src="{{ asset('img/locked.png') }}" class="lock-icon" alt="{{ __('locked') }}" title="{{ __('Locked') }}">@endif
                    @if ($thread->trashed())<span title="{{ __('Deleted') }}">🗑️</span>@endif
                    {{ $thread->title }}
                </div>
                <div class="meta thread-meta-line">
                    {{-- Original layout: "date, by last poster" on the left, post counter on the right --}}
                    <span class="last-post" title="{{ $thread->last_post_at?->isoFormat('LLL') }}">
                        {!! __(':date, by :name', [
                            'date' => e($thread->last_post_at?->shortDate()),
                            'name' => $thread->lastPoster ? '<span style="color: '.e($thread->lastPoster->displayColor()).'">'.e($thread->lastPoster->name).'</span>' : '—',
                        ]) !!}
                    </span>
                    <span class="count {{ auth()->check() ? ($lastRead === null ? 'count-new' : ($read ? 'count-read' : '')) : '' }}" title="{{ auth()->check() && $lastRead === null ? __('Never opened') : __('Posts') }}">{{ $thread->posts_count }}</span>
                </div>
            @if ($selectMode)
                </div>
            </label>
            @else
            </a>
            @endif
        @empty
            <div class="empty">
                {{ __('No threads yet.') }}
                @auth @can('createThread', $section)<br><br><a class="btn primary" href="{{ route('threads.create', $section) }}" wire:navigate>{{ __('Start the first one') }}</a>@endcan @endauth
            </div>
        @endforelse
    </div>
    {{-- Fixed actions at the bottom of the left bar, like the original forum --}}
    <div class="panel-foot">
        @auth
            @can('createThread', $section)
                <a class="foot-btn new-thread" href="{{ route('threads.create', $section) }}" wire:navigate><x-art name="new-thread" /> {{ __('New thread') }}</a>
            @endcan
        @else
            <a class="foot-btn new-thread" href="{{ route('login') }}" x-data x-on:click.prevent="$dispatch('open-modal', { name: 'auth', tab: 'login' })"><x-art name="new-thread" /> {{ __('New thread') }}</a>
        @endauth
        <a class="foot-btn" href="{{ route('home') }}" wire:navigate><x-art name="sections" /> {{ __('All sections') }}</a>
    </div>
</div>
