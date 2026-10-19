@php($statusLabels = ['open' => __('Open'), 'resolved' => __('Resolved'), 'dismissed' => __('Dismissed')])
<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head">
        <h1 class="with-art"><x-art name="profile" /> {{ __('User file') }}: <x-user-name :user="$user" /></h1>
        <span style="display:flex;gap:6px">
            <a class="btn small" href="{{ route('moderation', ['tab' => 'search', 'user' => $user->name]) }}" wire:navigate>🔎 {{ __('Their posts') }}</a>
            @can('sanction', $user)<button type="button" class="btn small danger" x-on:click="$dispatch('open-sanction', { userId: {{ $user->id }} })">⚖️ {{ __('Sanction…') }}</button>@endcan
        </span>
    </div>
    <div class="scroll settings-page">
        <div class="settings-card">
            <div class="health-grid">
                <div class="health-tile"><div class="k">{{ __('Status') }}</div><div class="v">
                    @if ($user->status === 'deleted'){{ __('Anonymized') }}
                    @elseif ($user->isBanned())<span style="color:var(--danger)">{{ __('Suspended') }}</span>
                    @elseif ($user->isMuted())<span style="color:var(--warning, #f2c14e)">{{ __('Muted') }}</span>
                    @else{{ __('Active') }}@endif
                </div></div>
                <div class="health-tile"><div class="k">{{ __('Registered') }}</div><div class="v" title="{{ $user->created_at?->isoFormat('LLL') }}">{{ $user->created_at?->shortDate() }}</div></div>
                <div class="health-tile"><div class="k">{{ __('Last seen') }}</div><div class="v">{{ $user->last_seen_at?->diffForHumans() ?? '—' }}</div></div>
                <div class="health-tile"><div class="k">{{ __('Email') }}</div><div class="v">{{ $user->hasVerifiedEmail() ? __('Confirmed') : __('Not confirmed') }}</div></div>
                <div class="health-tile"><div class="k">{{ __('Threads') }} / {{ __('Posts') }}</div><div class="v">{{ $user->threads_count }} / {{ $user->posts_count }}</div></div>
            </div>
            <div class="health-grid">
                <div class="health-tile"><div class="k">{{ __('Hidden posts') }}</div><div class="v">{{ $summary['posts']['hidden'] }}</div></div>
                <div class="health-tile"><div class="k">{{ __('Moderated posts') }}</div><div class="v">{{ $summary['posts']['moderated'] }}</div></div>
                <div class="health-tile"><div class="k">{{ __('Deleted posts') }}</div><div class="v">{{ $summary['posts']['deleted'] }}</div></div>
                <div class="health-tile"><div class="k">{{ __('Reports received') }}</div><div class="v">{{ array_sum($summary['received']) }} <span class="muted" style="font-size:11px">({{ __(':count open', ['count' => $summary['received']['open'] ?? 0]) }})</span></div></div>
                <div class="health-tile" title="{{ __('A high share of dismissed reports can mean abusive reporting.') }}"><div class="k">{{ __('Reports sent') }}</div><div class="v">{{ array_sum($summary['sent']) }} <span class="muted" style="font-size:11px">({{ __(':count dismissed', ['count' => $summary['sent']['dismissed'] ?? 0]) }})</span></div></div>
            </div>
        </div>

        <div class="settings-card">
            <h3>🗒️ {{ __('Staff notes') }}</h3>
            <p class="muted" style="font-size:12px;margin:4px 0 8px">{{ __('Only moderators can read these notes. The user never sees them.') }}</p>
            <form wire:submit="addNote" class="note-form">
                <textarea wire:model="note" rows="2" maxlength="2000" placeholder="{{ __('Context for the other moderators: warnings given in private, suspicions, agreements…') }}" aria-label="{{ __('New note') }}"></textarea>
                <button type="submit" class="btn primary">{{ __('Add') }}</button>
            </form>
            @error('note')<div class="error">{{ $message }}</div>@enderror
            @forelse ($notes as $n)
                <div class="staff-note" wire:key="n{{ $n->id }}">
                    <div class="meta"><x-user-name :user="$n->author" /> · <span title="{{ $n->created_at->isoFormat('LLL') }}">{{ $n->created_at->shortDateTime() }}</span>
                        @if ($n->author_id === auth()->id() || auth()->user()->can('admin'))
                            <button type="button" class="linkbtn danger" x-on:click="$confirm(@js(__('Delete this note?')), () => $wire.deleteNote({{ $n->id }}), { title: @js(__('Delete')), label: @js(__('Delete')) })">{{ __('Delete') }}</button>
                        @endif
                    </div>
                    <div class="body">{{ $n->body }}</div>
                </div>
            @empty
                <div class="empty">{{ __('No notes yet.') }}</div>
            @endforelse
        </div>

        <div class="settings-card">
            <h3>⚖️ {{ __('Sanctions') }}</h3>
            <table class="grid">
                <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Reason') }}</th><th>{{ __('By') }}</th><th>{{ __('When') }}</th><th>{{ __('Until') }}</th></tr></thead>
                <tbody>
                @forelse ($sanctions as $s)
                    <tr wire:key="s{{ $s->id }}" class="{{ $s->revoked_at || ($s->expires_at && $s->expires_at->isPast()) ? 'muted' : '' }}">
                        <td>{{ __(ucfirst($s->type)) }}</td>
                        <td>{{ $s->reason }}</td>
                        <td><x-user-name :user="$s->moderator" /></td>
                        <td class="muted">{{ $s->created_at->shortDateTime() }}</td>
                        <td class="muted">
                            @if ($s->revoked_at){{ __('Revoked by :name', ['name' => $s->revoker?->name ?? '—']) }}
                            @elseif ($s->type === 'warning')—
                            @else{{ $s->expires_at?->shortDateTime() ?? __('Permanently') }}@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">{{ __('No sanctions.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="settings-card">
            <h3>🚩 {{ __('Latest reports about their posts') }}</h3>
            <table class="grid">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Thread') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @forelse ($summary['receivedLatest'] as $r)
                    <tr wire:key="r{{ $r->id }}">
                        <td class="muted">{{ $r->created_at->shortDateTime() }}</td>
                        <td><a href="{{ route('moderation.reports.show', $r) }}" wire:navigate>{{ $r->post?->thread?->title ?? '—' }}</a></td>
                        <td>{{ \Illuminate\Support\Str::limit($r->reason, 80) }}</td>
                        <td>{{ $statusLabels[$r->status] ?? $r->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">{{ __('No reports.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($canSeeIps)
            <div class="settings-card">
                <h3>🌐 {{ __('IP addresses & related accounts') }}</h3>
                <p class="muted" style="font-size:12px;margin:4px 0">{{ __('registered from :ip', ['ip' => $user->registration_ip ?? '—']) }} · {{ __('last IP :ip', ['ip' => $user->last_ip ?? '—']) }}</p>
                <table class="grid">
                    <thead><tr><th>IP</th><th>{{ __('Requests') }}</th><th>{{ __('Last seen') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($ips as $row)
                        <tr>
                            <td><code>{{ $row->ip }}</code></td>
                            <td>{{ $row->hits }}</td>
                            <td class="muted">{{ \Illuminate\Support\Carbon::parse($row->last_seen_at)->diffForHumans() }}</td>
                            <td><a href="{{ route('moderation', ['tab' => 'search', 'ip' => $row->ip]) }}" wire:navigate>{{ __('Posts from this IP') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('No address recorded yet.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <h3 class="history-title">{{ __('Other accounts on the same addresses') }}</h3>
                <div class="chips">
                    @forelse ($shared as $other)
                        <a class="chip" href="{{ route('moderation.users.show', $other) }}" wire:navigate style="color: {{ $other->displayColor() }}">
                            {{ $other->name }} @if ($other->isBanned())<span style="color:var(--danger)">· {{ __('suspended') }}</span>@endif
                        </a>
                    @empty
                        <span class="muted" style="font-size:11px">{{ __('None found.') }}</span>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</div>
