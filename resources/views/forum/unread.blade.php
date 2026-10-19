<x-layouts.app :title="__('Unread')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <div class="panel-head"><h1>{{ __('Threads with new posts') }}</h1><livewire:mark-all-read />@include('partials.pager-links', ['paginator' => $threads])</div>
    <div class="scroll">
        @forelse ($threads as $thread)
            <a class="ui-content" href="{{ route('threads.unread', $thread) }}" wire:navigate>
                <div class="row"><span class="title">{{ $thread->title }}</span><span class="muted">{{ $thread->section->name }}</span></div>
                <div class="meta"><span class="count">{{ $thread->posts_count }}</span><span>{{ $thread->last_post_at?->diffForHumans() }}</span>
                    @if ($thread->lastPoster)<span style="color: {{ $thread->lastPoster->displayColor() }}">{{ $thread->lastPoster->name }}</span>@endif</div>
            </a>
        @empty
            <div class="empty">{{ __("You're all caught up.") }}</div>
        @endforelse
    </div>
</x-layouts.app>
