<x-layouts.app :title="__('Bookmarks')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <div class="panel-head"><h1 class="with-art"><x-art name="bookmarks" /> {{ __('Bookmarks') }}</h1>@include('partials.pager-links', ['paginator' => $posts])</div>
    <div class="scroll">
        @forelse ($posts as $post)
            <a class="ui-content" href="{{ route('posts.show', $post) }}" wire:navigate>
                <div class="row"><span class="title">{{ $post->thread->title }}</span><span class="muted">{{ $post->thread->section->name }}</span></div>
                <div style="margin-top:4px">{{ $post->excerpt() }}</div>
                <div class="meta"><span style="color: {{ $post->user?->displayColor() }}">{{ $post->user?->name }}</span><span>{{ $post->created_at->shortDateTime() }}</span><span>· {{ __('saved :when', ['when' => \Illuminate\Support\Carbon::parse($post->pivot->created_at)->diffForHumans()]) }}</span></div>
            </a>
        @empty
            <div class="empty">{{ __('No bookmarks yet. Use “Bookmark” in the menu of a post (click the author name).') }}</div>
        @endforelse
    </div>
</x-layouts.app>
