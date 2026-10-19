<div style="display:flex;flex-direction:column;min-height:0;flex:1" wire:poll.30s>
    <div class="panel-head">
        <div class="tabs">
            <a href="#" class="{{ $tab === 'friends' ? 'active' : '' }}" wire:click.prevent="$set('tab', 'friends')">🤝 {{ __('Friends') }} ({{ $friends->count() }})</a>
            <a href="#" class="{{ $tab === 'requests' ? 'active' : '' }}" wire:click.prevent="$set('tab', 'requests')">{{ __('Requests') }} @if ($received->count())<span class="badge">{{ $received->count() }}</span>@endif</a>
            <a href="#" class="{{ $tab === 'ignored' ? 'active' : '' }}" wire:click.prevent="$set('tab', 'ignored')">🙈 {{ __('Ignored') }} ({{ $ignored->count() }})</a>
            <a href="#" class="{{ $tab === 'blocked' ? 'active' : '' }}" wire:click.prevent="$set('tab', 'blocked')">⛔ {{ __('Blocked') }} ({{ $blocked->count() }})</a>
        </div>
        <form wire:submit="add" style="display:flex;gap:4px">
            <input type="text" wire:model="addName" placeholder="{{ __('Nickname') }}" aria-label="{{ __('Nickname') }}" data-mention-input>
            <button class="btn small primary" type="submit">➕ {{ __('Add friend') }}</button>
        </form>
    </div>
    <div class="scroll">
        @error('addName')<div class="flash" style="background:#4a1622;color:#ffc2cf">{{ $message }}</div>@enderror
        @error('friend')<div class="flash" style="background:#4a1622;color:#ffc2cf">{{ $message }}</div>@enderror
        @if ($tab === 'friends')
            @forelse ($friends as $friend)
                <div class="ui-content section-item" wire:key="f{{ $friend->id }}">
                    <img src="{{ $friend->avatar() }}" alt="" style="width:36px;height:36px;border-radius:50%">
                    <div style="flex:1">
                        <span class="online-dot {{ $friend->isOnline() ? 'on' : '' }}"></span><x-user-name :user="$friend" />
                        <div class="meta">{{ $friend->isOnline() ? __('online now') : ($friend->hide_online ? __('status hidden') : ($friend->last_seen_at ? __('seen :when', ['when' => $friend->last_seen_at->diffForHumans()]) : '—')) }}</div>
                    </div>
                    <a class="icon-btn" href="{{ route('messages', ['to' => $friend->name]) }}" wire:navigate title="{{ __('Send a message') }}">✉️</a>
                    <button type="button" class="icon-btn" x-data x-on:click="$confirm(@js(__('Remove :name from your friends?', ['name' => $friend->name])), () => $wire.remove({{ $friend->id }}), { label: @js(__('Remove')) })" title="{{ __('Remove') }}">✕</button>
                </div>
            @empty
                <div class="empty">{{ __('No friends yet. Add users with the field above or from their profile.') }}</div>
            @endforelse
        @elseif ($tab === 'requests')
            <h3 class="history-title" style="margin-top:0;border:0">{{ __('Received') }}</h3>
            @forelse ($received as $request)
                <div class="ui-content section-item" wire:key="rq{{ $request->id }}">
                    <x-user-name :user="$request->requester" />
                    <span class="muted" style="flex:1">{{ $request->created_at->diffForHumans() }}</span>
                    <button type="button" class="btn small primary" wire:click="accept({{ $request->id }})">{{ __('Accept') }}</button>
                    <button type="button" class="btn small" wire:click="remove({{ $request->user_id }})">{{ __('Decline') }}</button>
                </div>
            @empty
                <div class="muted" style="padding:6px">{{ __('No pending request.') }}</div>
            @endforelse
            <h3 class="history-title">{{ __('Sent') }}</h3>
            @forelse ($sent as $request)
                <div class="ui-content section-item" wire:key="rs{{ $request->id }}">
                    <x-user-name :user="$request->recipient" />
                    <span class="muted" style="flex:1">{{ $request->created_at->diffForHumans() }}</span>
                    <button type="button" class="btn small" wire:click="remove({{ $request->friend_id }})">{{ __('Cancel') }}</button>
                </div>
            @empty
                <div class="muted" style="padding:6px">{{ __('No pending request.') }}</div>
            @endforelse
        @elseif ($tab === 'blocked')
            <p class="muted" style="font-size:11px;padding:0 6px">{{ __('Blocked users cannot message you, mention or quote you, react to your posts or send you friend requests.') }}</p>
            @forelse ($blocked as $person)
                <div class="ui-content section-item" wire:key="bl{{ $person->id }}">
                    <x-user-name :user="$person" />
                    <span class="muted" style="flex:1">{{ \Illuminate\Support\Carbon::parse($person->pivot->created_at)->diffForHumans() }}</span>
                    <button type="button" class="btn small" wire:click="unblock({{ $person->id }})">{{ __('Unblock') }}</button>
                </div>
            @empty
                <div class="empty">{{ __('You have not blocked anyone.') }}</div>
            @endforelse
        @else
            @forelse ($ignored as $person)
                <div class="ui-content section-item" wire:key="ig{{ $person->id }}">
                    <x-user-name :user="$person" />
                    <span style="flex:1"></span>
                    <button type="button" class="btn small" wire:click="unignore({{ $person->id }})">{{ __('Stop ignoring') }}</button>
                </div>
            @empty
                <div class="empty">{{ __('You are not ignoring anyone.') }}</div>
            @endforelse
        @endif
    </div>
</div>
