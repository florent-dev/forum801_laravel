<x-layouts.app :title="$section->name" :drawer-open="true">
    <x-slot:left><livewire:thread-list :section="$section" /></x-slot:left>
    <div class="panel-head">
        <h1>{{ $section->name }}</h1>
        <span>
            @if ($guild = $section->guild)
                <a class="btn small" href="{{ route('guilds.show', $guild) }}" wire:navigate><x-art name="guilds" size="16" /> {{ __('Guild page') }}</a>
                @can('manageMembers', $guild)
                    @php($pending = $guild->applications()->where('status', 'pending')->count())
                    <a class="btn small" href="{{ route('guilds.manage', [$guild, 'tab' => $pending ? 'applications' : 'members']) }}" wire:navigate><x-art name="settings" size="16" /> {{ __('Manage') }}@if ($pending) <span class="badge" title="{{ __('Applications waiting') }}">{{ $pending }}</span>@endif</a>
                @endcan
            @endif
            {{-- Feeds only exist for public sections --}}
            @unless ($section->isPrivate())
                <a class="btn small" href="{{ route('feeds.section', $section) }}" title="RSS">📡 RSS</a>
            @endunless
            @can('update', $section)<a class="btn small" href="{{ route('sections.settings', $section) }}" wire:navigate>{{ __('Section settings') }}</a>@endcan
        </span>
    </div>
    <div class="scroll">
        <div class="form-box section-item">
            <img class="icon" src="{{ $section->iconUrl() }}" alt="">
            <div>
                <div>{{ $section->guild ? __('Forum of the :name guild.', ['name' => $section->guild->name]) : $section->description }}</div>
                <div class="muted" style="font-size:10px;margin-top:4px">{{ trans_choice(':count thread|:count threads', $threadCount = $section->threads()->count(), ['count' => $threadCount]) }} · {{ $section->isPrivate() ? __('Private section') : __('Public section') }}</div>
            </div>
        </div>
        <div class="empty">{{ __('Pick a thread on the left to start reading.') }}</div>
    </div>
</x-layouts.app>
