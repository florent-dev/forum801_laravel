<x-layouts.app :title="__('Followed threads')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <div class="panel-head"><h1>{{ __('Followed threads') }}</h1>@include('partials.pager-links', ['paginator' => $threads])</div>
    <div class="scroll">
        @forelse ($threads as $thread)
            <a class="ui-content" href="{{ $thread->url() }}" wire:navigate>
                <div class="row"><span class="title">{{ $thread->title }}</span><span class="muted">{{ $thread->section->name }}</span></div>
                <div class="meta"><span class="count">{{ $thread->posts_count }}</span><span>{{ $thread->last_post_at?->diffForHumans() }}</span>
                    @if ($thread->lastPoster)<span style="color: {{ $thread->lastPoster->displayColor() }}">{{ $thread->lastPoster->name }}</span>@endif</div>
            </a>
        @empty
            <div class="empty">{{ __("You don't follow any thread yet. You automatically follow threads you post in.") }}</div>
        @endforelse
    </div>
</x-layouts.app>
