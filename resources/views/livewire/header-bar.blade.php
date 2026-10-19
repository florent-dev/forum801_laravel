<div class="header-bar" wire:poll.20s>
    <a class="hb-item" href="{{ route('unread') }}" wire:navigate title="{{ __('Threads with new posts') }}">
        <x-art name="unread" class="hb-icon" /><span class="hb-label">{{ __('Unread') }}</span>
        @if ($unread)<span class="badge soft">{{ $unread }}</span>@endif
    </a>
    <a class="hb-item" href="{{ route('messages') }}" wire:navigate title="{{ __('Private messages') }}">
        <x-art name="messages" class="hb-icon" /><span class="hb-label">{{ __('Messages') }}</span>
        @if ($messages)<span class="badge">{{ $messages }}</span>@endif
    </a>
    @if ($reports !== null)
        <a class="hb-item" href="{{ route('moderation') }}" wire:navigate title="{{ __('Open reports') }}">
            <x-art name="moderation" class="hb-icon" /><span class="hb-label">{{ __('Moderation') }}</span>
            @if ($reports)<span class="badge">{{ $reports }}</span>@endif
        </a>
    @endif

    <div class="dropdown" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
        <button type="button" class="hb-item" x-on:click="open = !open" title="{{ __('Notifications') }}" aria-haspopup="true" :aria-expanded="open">
            <x-art name="notifications" class="hb-icon" />
            @if ($notificationCount)<span class="badge">{{ $notificationCount }}</span>@endif
        </button>
        <div class="dropdown-menu wide" x-show="open" x-cloak x-transition.origin.top.right>
            <div class="dropdown-head">
                <span>{{ __('Notifications') }}</span>
                @if ($notificationCount)<button type="button" class="linkbtn" wire:click="markAllNotificationsRead">{{ __('Mark all read') }}</button>@endif
            </div>
            @forelse ($notifications as $n)
                <a class="notif {{ $n->read_at ? '' : 'unread' }}" href="{{ route('notifications.open', $n->id) }}">
                    <span class="notif-kind"><x-art :name="['reply' => 'unread', 'mention' => 'profile', 'quote' => 'unread', 'message' => 'messages', 'moderation' => 'moderation', 'friend' => 'friends', 'guild' => 'guilds'][$n->data['kind'] ?? ''] ?? 'notifications'" size="18" /></span>
                    <span>
                        <span class="notif-text">{{ __($n->data['key'] ?? ($n->data['text'] ?? ''), $n->data['params'] ?? []) }}</span>
                        <span class="notif-time">{{ $n->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <div class="empty">{{ __('Nothing new.') }}</div>
            @endforelse
            <a class="dropdown-foot" href="{{ route('notifications') }}" wire:navigate>{{ __('See all notifications') }}</a>
        </div>
    </div>

    <div class="dropdown" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
        <button type="button" class="hb-user" x-on:click="open = !open" aria-haspopup="true" :aria-expanded="open">
            <img src="{{ $user->avatar() }}" alt="">
            <span style="color: {{ $user->displayColor() }}">{{ $user->name }}</span> ▾
        </button>
        <div class="dropdown-menu" x-show="open" x-cloak x-transition.origin.top.right>
            <a href="{{ route('profile', $user) }}" wire:navigate><x-art name="profile" size="18" /> {{ __('My profile') }}</a>
            <a href="{{ route('settings') }}" wire:navigate><x-art name="settings" size="18" /> {{ __('Settings') }}</a>
            <a href="{{ route('friends') }}" wire:navigate><x-art name="friends" size="18" /> {{ __('Friends') }}</a>
            <a href="{{ route('bookmarks') }}" wire:navigate><x-art name="bookmarks" size="18" /> {{ __('Bookmarks') }}</a>
            <a href="{{ route('subscriptions') }}" wire:navigate><x-art name="followed" size="18" /> {{ __('Followed threads') }}</a>
            <a href="{{ route('messages') }}" wire:navigate><x-art name="messages" size="18" /> {{ __('Messages') }}</a>
            <a href="{{ route('help') }}" wire:navigate><x-art name="help" size="18" /> {{ __('Help & tips') }}</a>
            <a href="{{ route('notifications') }}" wire:navigate><x-art name="notifications" size="18" /> {{ __('Notifications') }}</a>
            @can('access-moderation')<a href="{{ route('moderation') }}" wire:navigate><x-art name="moderation" size="18" /> {{ __('Moderation') }}</a>@endcan
            @can('admin')<a href="{{ route('admin.users') }}" wire:navigate><x-art name="admin" size="18" /> {{ __('Administration') }}</a>@endcan
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-art name="logout" size="18" /> {{ __('Log out') }}</button></form>
        </div>
    </div>
</div>
