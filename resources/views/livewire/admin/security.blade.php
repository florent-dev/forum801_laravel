<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head">
        <div class="tabs">
            @foreach (['events' => '📜 '.__('Events'), 'lookup' => '🔎 '.__('IP lookup'), 'suspicious' => '🚨 '.__('Suspicious activity'), 'bans' => '⛔ '.__('IP bans')] as $key => $label)
                <a href="#" class="{{ $tab === $key ? 'active' : '' }}" wire:click.prevent="$set('tab', '{{ $key }}')">{{ $label }}</a>
            @endforeach
        </div>
        @if ($tab === 'events'){{ $events->links() }}@endif
    </div>
    <div class="scroll">
        @if ($tab === 'events')
            <div class="search-summary">
                <select wire:model.live="type" aria-label="{{ __('Type') }}" style="width:auto">
                    <option value="">{{ __('All events') }}</option>
                    @foreach ($types as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
                <select wire:model.live="severity" aria-label="{{ __('Severity') }}" style="width:auto">
                    <option value="">{{ __('All severities') }}</option>
                    @foreach (['info', 'warning', 'critical'] as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                </select>
                <input type="text" wire:model.live.debounce.400ms="ip" placeholder="IP" style="width:150px">
                <input type="text" wire:model.live.debounce.400ms="user" placeholder="{{ __('Nickname') }}" style="width:150px" data-mention-input>
            </div>
            <table class="grid">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Event') }}</th><th>{{ __('User') }}</th><th>IP</th><th>{{ __('Details') }}</th></tr></thead>
                <tbody>
                @forelse ($events as $event)
                    <tr wire:key="se{{ $event->id }}">
                        <td class="muted" style="white-space:nowrap">{{ $event->created_at->isoFormat('L LTS') }}</td>
                        <td><span class="pill sev-{{ $event->severity }}">{{ $event->type }}</span></td>
                        <td>@if ($event->user)<x-user-name :user="$event->user" />@else<span class="muted">—</span>@endif</td>
                        <td>@if ($event->ip)<a href="#" wire:click.prevent="$set('ip', '{{ $event->ip }}')"><code>{{ $event->ip }}</code></a>@endif</td>
                        <td class="muted" title="{{ $event->user_agent }}">@foreach ((array) $event->details as $k => $v){{ $k }}: {{ is_scalar($v) ? $v : json_encode($v) }}@if (! $loop->last), @endif @endforeach</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">{{ __('No event matches these filters.') }}</td></tr>
                @endforelse
                </tbody>
            </table>

        @elseif ($tab === 'lookup')
            <div class="search-summary">
                <input type="text" wire:model.live.debounce.400ms="ip" placeholder="{{ __('IP address') }}" style="width:220px" autofocus>
                @if ($ip !== '')<button type="button" class="btn small danger" wire:click="banAddress('{{ $ip }}')">⛔ {{ __('Ban this address…') }}</button>@endif
            </div>
            @if ($ip !== '')
                @if ($ipBan)<div class="ban-banner" style="margin:0 5px 10px">{{ __('Banned: :reason', ['reason' => $ipBan->reason]) }}</div>@endif
                <h3 class="history-title" style="margin:4px 5px">{{ __('Accounts seen on :ip', ['ip' => $ip]) }}</h3>
                <table class="grid">
                    <thead><tr><th>{{ __('User') }}</th><th>{{ __('Requests') }}</th><th>{{ __('First seen') }}</th><th>{{ __('Last seen') }}</th></tr></thead>
                    <tbody>
                    @forelse ($accounts as $row)
                        <tr><td><a href="{{ route('profile', $row->name) }}" wire:navigate>{{ $row->name }}</a></td><td>{{ $row->hits }}</td>
                            <td class="muted">{{ \Illuminate\Support\Carbon::parse($row->first_seen_at)->shortDateTime() }}</td>
                            <td class="muted">{{ \Illuminate\Support\Carbon::parse($row->last_seen_at)->diffForHumans() }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('No account used this address.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <h3 class="history-title" style="margin:10px 5px 4px">{{ __('Recent events from this address') }}</h3>
                @foreach ($ipEvents as $event)
                    <div class="sanction-row"><span class="pill sev-{{ $event->severity }}">{{ $event->type }}</span><span>{{ $event->user?->name ?? '—' }}</span><span class="muted">{{ $event->created_at->diffForHumans() }}</span></div>
                @endforeach
            @else
                <div class="empty">{{ __('Type an address to see which accounts used it.') }}</div>
            @endif

        @elseif ($tab === 'suspicious')
            <div class="profile-columns" style="grid-template-columns:1fr 1fr">
                <div class="profile-card">
                    <h3>🔐 {{ __('Failed logins in the last 24 hours') }}</h3>
                    @forelse ($failedByIp as $row)
                        <div class="sanction-row"><code>{{ $row->ip }}</code><span style="flex:1">{{ trans_choice(':count attempt|:count attempts', $row->attempts) }} · {{ trans_choice(':count nickname|:count nicknames', $row->names) }}</span>
                            <a href="#" wire:click.prevent="$set('ip', '{{ $row->ip }}'); $set('tab', 'lookup')">{{ __('Look up') }}</a>
                            <button type="button" class="linkbtn" wire:click="banAddress('{{ $row->ip }}')">{{ __('Ban') }}</button></div>
                    @empty
                        <div class="muted">{{ __('Nothing suspicious.') }}</div>
                    @endforelse
                </div>
                <div class="profile-card">
                    <h3>👥 {{ __('Addresses shared by 3 accounts or more (30 days)') }}</h3>
                    @forelse ($crowdedIps as $row)
                        <div class="sanction-row"><code>{{ $row->ip }}</code><span style="flex:1">{{ trans_choice(':count account|:count accounts', $row->accounts) }}</span>
                            <a href="#" wire:click.prevent="$set('ip', '{{ $row->ip }}'); $set('tab', 'lookup')">{{ __('Look up') }}</a></div>
                    @empty
                        <div class="muted">{{ __('Nothing suspicious.') }}</div>
                    @endforelse
                </div>
            </div>
            <div class="profile-card" style="margin:0 5px">
                <h3>🕵️ {{ __('Possible ban evasion') }}</h3>
                @forelse ($evasions as $event)
                    <div class="sanction-row"><x-user-name :user="$event->user" /><span style="flex:1" class="muted">{{ __('shares :ip with :names', ['ip' => $event->ip, 'names' => implode(', ', $event->details['shared_with'] ?? [])]) }}</span><span class="muted">{{ $event->created_at->diffForHumans() }}</span></div>
                @empty
                    <div class="muted">{{ __('Nothing suspicious.') }}</div>
                @endforelse
            </div>

        @else
            <form class="settings-card" wire:submit="addBan" style="margin:0 5px 12px">
                <h3>⛔ {{ __('Ban an address or a range') }}</h3>
                <div class="search-filters-grid" style="padding:8px 0">
                    <label>{{ __('IP or range') }}<input type="text" wire:model="banIp" placeholder="203.0.113.7 / 203.0.113.0/24">@error('banIp')<span class="error">{{ $message }}</span>@enderror</label>
                    <label>{{ __('Reason') }}<input type="text" wire:model="banReason" maxlength="500">@error('banReason')<span class="error">{{ $message }}</span>@enderror</label>
                    <label>{{ __('Duration in days (empty = permanent)') }}<input type="text" wire:model="banDays" inputmode="numeric">@error('banDays')<span class="error">{{ $message }}</span>@enderror</label>
                </div>
                <div class="settings-actions"><button class="btn danger" type="submit">{{ __('Ban') }}</button></div>
            </form>
            <table class="grid">
                <thead><tr><th>IP</th><th>{{ __('Reason') }}</th><th>{{ __('By') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($bans as $ban)
                    <tr wire:key="ib{{ $ban->id }}" @if ($ban->expires_at && $ban->expires_at->isPast()) style="opacity:.5" @endif>
                        <td><code>{{ $ban->ip }}</code></td><td>{{ $ban->reason }}</td><td>{{ $ban->creator?->name ?? '—' }}</td>
                        <td class="muted">{{ $ban->expires_at ? $ban->expires_at->shortDateTime() : __('never') }}</td>
                        <td><button type="button" class="linkbtn" x-data x-on:click="$confirm(@js(__('Lift the ban on :ip?', ['ip' => $ban->ip])), () => $wire.removeBan({{ $ban->id }}), { label: @js(__('Lift')), danger: false })">{{ __('Lift') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">{{ __('No address is banned.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>
