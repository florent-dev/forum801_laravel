<div style="display:flex;flex-direction:column;min-height:0;flex:1" wire:poll.20s>
    <div class="panel-head">
        <h1>{{ __('Private messages') }}</h1>
        <div style="display:flex;gap:6px;align-items:center">
            {{ $conversations->links() }}
            <button type="button" class="btn small primary" x-data x-on:click="$dispatch('open-modal', 'compose')">✉️ {{ __('New message') }}</button>
        </div>
    </div>
    <div class="scroll">
        @forelse ($conversations as $c)
            @php($unread = ! $c->pivot->last_read_at || ($c->last_message_at && $c->last_message_at->gt($c->pivot->last_read_at)))
            @php($last = $c->messages->first())
            <a class="ui-content {{ $unread ? '' : 'read' }}" href="{{ route('messages.show', $c) }}" wire:navigate wire:key="c{{ $c->id }}">
                <div class="row">
                    <span class="title">@if ($unread)<span class="unread-dot"></span>@endif{{ $c->subject }}</span>
                    <span class="muted">{{ $c->last_message_at?->diffForHumans() }}</span>
                </div>
                <div class="meta">
                    @foreach ($c->activeParticipants->where('id', '!=', auth()->id()) as $p)
                        <span style="color: {{ $p->displayColor() }}">{{ $p->name }}</span>
                    @endforeach
                    @if ($last)<span class="muted">— {{ Str::limit($last->body, 70) }}</span>@endif
                </div>
            </a>
        @empty
            <div class="empty">{{ __('No conversations yet. Say hi to someone!') }} 🐭</div>
        @endforelse
    </div>

    <x-modal name="compose" :title="__('New message')" width="560px">
        <form wire:submit="send">
            <div class="field"><label for="pm-to">{{ __('To (nicknames, separated by commas)') }}</label><input id="pm-to" type="text" wire:model="recipients" data-mention-multi placeholder="alice, bob">@error('recipients')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="pm-subject">{{ __('Subject') }}</label><input id="pm-subject" type="text" wire:model="subject" maxlength="120">@error('subject')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="pm-body">{{ __('Message') }}</label><x-editor id="pm-body" model="body" rows="6" /></div>
            <div class="modal-actions">
                <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'compose')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn primary" wire:loading.attr="disabled">{{ __('Send') }}</button>
            </div>
        </form>
    </x-modal>
</div>
