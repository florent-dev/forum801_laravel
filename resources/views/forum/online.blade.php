<x-layouts.app :title="__('Who is online')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <div class="panel-head"><h1>{{ __('Online in the last 5 minutes (:count)', ['count' => $users->count()]) }}</h1></div>
    <div class="scroll">
        @forelse ($users as $u)
            <a class="ui-content section-item" href="{{ route('profile', $u) }}" wire:navigate>
                <img src="{{ $u->avatar() }}" alt="" style="width:32px;height:32px;border-radius:50%">
                <div style="flex:1">
                    <span class="online-dot on"></span><span style="color: {{ $u->displayColor() }}">{{ $u->name }}</span>
                    <div class="meta">{{ __('Active :time', ['time' => $u->last_seen_at->diffForHumans()]) }}</div>
                </div>
            </a>
        @empty
            <div class="empty">{{ __('Nobody is online right now.') }}</div>
        @endforelse
    </div>
</x-layouts.app>
