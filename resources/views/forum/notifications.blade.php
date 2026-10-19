<x-layouts.app :title="__('Notifications')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <div class="panel-head"><h1>{{ __('Notifications') }}</h1>@include('partials.pager-links', ['paginator' => $notifications])</div>
    <div class="scroll">
        @forelse ($notifications as $n)
            <a class="ui-content notif {{ $n->read_at ? 'read' : 'unread' }}" href="{{ route('notifications.open', $n->id) }}">
                <span class="notif-kind"><x-art :name="['reply' => 'unread', 'mention' => 'profile', 'quote' => 'unread', 'message' => 'messages', 'moderation' => 'moderation', 'friend' => 'friends', 'guild' => 'guilds'][$n->data['kind'] ?? ''] ?? 'notifications'" size="18" /></span>
                <span>
                    <span class="notif-text title">{{ isset($n->data['key']) ? __($n->data['key'], $n->data['params'] ?? []) : ($n->data['text'] ?? '') }}</span>
                    <span class="notif-time">{{ $n->created_at->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <div class="empty">{{ __('No notifications yet. Follow threads with ☆ to get notified of replies.') }}</div>
        @endforelse
    </div>
</x-layouts.app>
