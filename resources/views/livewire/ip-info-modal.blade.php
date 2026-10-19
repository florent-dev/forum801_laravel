<div>
    <x-modal name="ip-info" :title="__('IP addresses & related accounts')" width="620px">
        @if ($user)
            <p style="margin-top:0">{{ __('User:') }} <x-user-name :user="$user" />
                <span class="muted">· {{ __('registered from :ip', ['ip' => $user->registration_ip ?? '—']) }} · {{ __('last IP :ip', ['ip' => $user->last_ip ?? '—']) }}</span>
            </p>
            @if ($postIp)<p class="flash" style="margin:0 0 10px">{{ __('This post was written from :ip', ['ip' => $postIp]) }}</p>@endif

            <h3 class="history-title" style="margin-top:4px">{{ __('Known addresses') }}</h3>
            <table class="grid">
                <thead><tr><th>IP</th><th>{{ __('Requests') }}</th><th>{{ __('First seen') }}</th><th>{{ __('Last seen') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($ips as $row)
                    <tr>
                        <td><code>{{ $row->ip }}</code></td>
                        <td>{{ $row->hits }}</td>
                        <td class="muted">{{ \Illuminate\Support\Carbon::parse($row->first_seen_at)->shortDateTime() }}</td>
                        <td class="muted">{{ \Illuminate\Support\Carbon::parse($row->last_seen_at)->diffForHumans() }}</td>
                        <td>@can('admin')<a href="{{ route('admin.security', ['tab' => 'lookup', 'ip' => $row->ip]) }}" wire:navigate>{{ __('Look up') }}</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('No address recorded yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>

            <h3 class="history-title">{{ __('Other accounts on the same addresses') }}</h3>
            <div class="chips">
                @forelse ($shared as $other)
                    <a class="chip" href="{{ route('profile', $other) }}" wire:navigate style="color: {{ $other->displayColor() }}">
                        {{ $other->name }} @if ($other->isBanned())<span style="color:var(--danger)">· {{ __('suspended') }}</span>@endif
                    </a>
                @empty
                    <span class="muted" style="font-size:11px">{{ __('None found.') }}</span>
                @endforelse
            </div>
        @endif
    </x-modal>
</div>
