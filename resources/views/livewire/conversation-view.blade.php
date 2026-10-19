<div style="display:flex;flex-direction:column;min-height:0;flex:1" wire:poll.20s
     x-data x-on:scroll-to-message.window="setTimeout(() => window.scrollInPanel(document.getElementById('msg-' + $event.detail.id), 'start', true), 80)">
    <div class="panel-head">
        <h1 class="with-art"><x-art name="messages" /> {{ $conversation->subject }}</h1>
        <div style="display:flex;gap:6px">
            <button type="button" class="btn small" x-on:click="$dispatch('open-modal', 'invite')">+ {{ __('Add user') }}</button>
            <button type="button" class="btn small danger" x-on:click="$confirm(@js(__('You will stop receiving messages from this conversation.')), () => $wire.leave(), { title: @js(__('Leave conversation?')), label: @js(__('Leave')) })">{{ __('Leave') }}</button>
        </div>
    </div>
    <div class="thread-meta">
        <span>{{ __('With:') }}</span>
        @foreach ($conversation->activeParticipants as $p)
            <a href="{{ route('profile', $p) }}" wire:navigate style="color: {{ $p->displayColor() }}"><span class="online-dot {{ $p->isOnline() ? 'on' : '' }}"></span>{{ $p->name }}</a>
        @endforeach
    </div>
    <div class="scroll">
        @if ($hasOlder)
            <div style="text-align:center;margin-bottom:8px"><button type="button" class="btn small" wire:click="loadOlder">{{ __('Load older messages') }}</button></div>
        @endif
        @foreach ($items as $m)
            <div class="conversation-msg {{ $m->user_id === auth()->id() ? 'mine' : '' }}" id="msg-{{ $m->id }}" wire:key="m{{ $m->id }}">
                <img class="avatar" src="{{ $m->user?->avatar() }}" alt="">
                <div class="bubble">
                    <div class="who"><x-user-name :user="$m->user" /> · {{ $m->created_at->shortDateTime() }}</div>
                    <div class="post-body">{!! $m->body_html !!}</div>
                </div>
            </div>
        @endforeach
        @can('reply', $conversation)
            <form class="form-box" wire:submit="send">
                <x-editor id="pm-reply" model="body" rows="4" draft="pm-{{ $conversation->id }}" :placeholder="__('Write a message… (Ctrl+Enter to send)')" />
                <div style="margin-top:6px"><button class="btn primary" type="submit" wire:loading.attr="disabled">{{ __('Send') }}</button></div>
            </form>
        @endcan
    </div>

    <x-modal name="invite" :title="__('Add a user')">
        <form wire:submit="invite">
            <div class="field"><label for="invite-name">{{ __('Nickname') }}</label><input id="invite-name" type="text" wire:model="inviteName">@error('inviteName')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="modal-actions">
                <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'invite')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn primary">{{ __('Add') }}</button>
            </div>
        </form>
    </x-modal>
</div>
