<div>
    <x-modal name="sanction" :title="__('Sanction a user')" width="560px">
        @if ($target)
            <p style="margin-top:0">{{ __('User:') }} <x-user-name :user="$target" />
                @if ($target->isBanned())<span class="pill" style="color:var(--danger)">{{ __('suspended') }}</span>@elseif ($target->isMuted())<span class="pill" style="color:var(--warn)">{{ __('muted') }}</span>@endif
            </p>
            <form wire:submit="apply">
                <div class="field">
                    <label>{{ __('Sanction') }}</label>
                    <div class="chips">
                        @foreach ($types as $value => $label)
                            <label class="chip {{ $type === $value ? 'chip-on' : '' }}"><input type="radio" wire:model.live="type" value="{{ $value }}" style="display:none"> {{ $label }}</label>
                        @endforeach
                    </div>
                    @error('type')<div class="error">{{ $message }}</div>@enderror
                </div>
                @if ($type !== 'warning')
                    <div class="field">
                        <label for="sanction-days">{{ __('Duration') }}</label>
                        <select id="sanction-days" wire:model="days">
                            @foreach ($durations as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('days')<div class="error">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="field">
                    <label for="sanction-reason">{{ __('Reason (shown to the user)') }}</label>
                    <input id="sanction-reason" type="text" wire:model="reason" maxlength="500">
                    @error('reason')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'sanction')">{{ __('Close') }}</button>
                    <button type="submit" class="btn danger" wire:loading.attr="disabled">{{ __('Apply sanction') }}</button>
                </div>
            </form>

            <h3 class="history-title">{{ __('History') }}</h3>
            @forelse ($history as $s)
                <div class="sanction-row {{ $s->isActive() ? 'active' : '' }}" wire:key="sa{{ $s->id }}">
                    <span class="pill" style="color: {{ ['warning' => 'var(--warn)', 'mute' => '#ed67ea', 'suspension' => 'var(--danger)'][$s->type] }}">{{ __(ucfirst($s->type)) }}</span>
                    <span style="flex:1">
                        {{ $s->reason }}
                        <span class="muted">· {{ $s->created_at->shortDate() }} · {{ $s->moderator?->name ?? '—' }}
                            @if ($s->type !== 'warning') · {{ $s->expires_at ? __('until :date', ['date' => $s->expires_at->shortDateTime()]) : __('permanent') }}@endif
                            @if ($s->revoked_at) · {{ __('lifted by :name', ['name' => $s->revoker?->name ?? '—']) }}@endif
                        </span>
                    </span>
                    @if ($s->isActive())
                        <button type="button" class="linkbtn" x-on:click="$confirm(@js(__('Lift this sanction now?')), () => $wire.revoke({{ $s->id }}), { title: @js(__('Lift sanction')), label: @js(__('Lift')), danger: false })">{{ __('Lift') }}</button>
                    @endif
                </div>
            @empty
                <div class="muted" style="font-size:11px">{{ __('No previous sanctions.') }}</div>
            @endforelse
        @endif
    </x-modal>
</div>
