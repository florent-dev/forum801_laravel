<div style="margin-top:10px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
    @auth
        @if ($self)
            <a class="btn small" href="{{ route('settings') }}" wire:navigate>⚙️ {{ __('Edit my profile') }}</a>
        @else
            @if (! $ignoring && ! $blocking && ! $blockedByOther)<a class="btn small primary" href="{{ route('messages', ['to' => $other->name]) }}" wire:navigate>✉️ {{ __('Send a message') }}</a>@endif
            @if (! $blocking && ! $blockedByOther)
            @switch ($status)
                @case('friends')
                    <button type="button" class="btn small" x-data x-on:click="$confirm(@js(__('Remove :name from your friends?', ['name' => $other->name])), () => $wire.removeFriend(), { label: @js(__('Remove')) })">🤝 {{ __('Friends') }} ✓</button>
                    @break
                @case('sent')
                    <button type="button" class="btn small" wire:click="removeFriend" title="{{ __('Cancel the request') }}">⏳ {{ __('Request sent') }}</button>
                    @break
                @case('received')
                    <button type="button" class="btn small primary" wire:click="addFriend">🤝 {{ __('Accept friend request') }}</button>
                    @break
                @default
                    <button type="button" class="btn small" wire:click="addFriend">➕ {{ __('Add friend') }}</button>
            @endswitch
            @endif
            @unless ($other->isStaff())
                <button type="button" class="btn small" wire:click="toggleIgnore">{{ $ignoring ? '👁️ '.__('Stop ignoring') : '🙈 '.__('Ignore') }}</button>
                @if ($blocking)
                    <button type="button" class="btn small" wire:click="toggleBlock">✅ {{ __('Unblock') }}</button>
                @else
                    <button type="button" class="btn small danger" x-data x-on:click="$confirm(@js(__(':name will no longer be able to message you, mention or quote you, react to your posts or send you friend requests. Their posts will be collapsed for you.', ['name' => $other->name])), () => $wire.toggleBlock(), { title: @js(__('Block this user?')), label: @js(__('Block')) })">⛔ {{ __('Block') }}</button>
                @endif
            @endunless
            @error('block')<div class="error" style="flex-basis:100%">{{ $message }}</div>@enderror
            @can('access-moderation')<a class="btn small" href="{{ route('moderation.users.show', $other) }}" wire:navigate>🗂️ {{ __('User file') }}</a>@endcan
            @can('sanction', $other)<button type="button" class="btn small danger" x-data x-on:click="$dispatch('open-sanction', { userId: {{ $other->id }} })">⚖️ {{ __('Sanction…') }}</button>@endcan
            @can('admin')<a class="btn small" href="{{ route('admin.users', ['search' => $other->name]) }}" wire:navigate>🔧 {{ __('Manage') }}</a>@endcan
        @endif
        @error('friend')<div class="error" style="flex-basis:100%">{{ $message }}</div>@enderror
        @error('ignore')<div class="error" style="flex-basis:100%">{{ $message }}</div>@enderror
    @endauth
</div>
