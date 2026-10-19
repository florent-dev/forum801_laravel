<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head">
        <h1>{{ __('Users') }}</h1>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name or email') }}">
        {{ $users->links() }}
    </div>
    <div class="scroll">
        <table class="grid">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last seen') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr wire:key="u{{ $user->id }}">
                    <td><x-user-name :user="$user" /></td>
                    <td class="muted">{{ $user->email }}</td>
                    <td>@foreach ($user->roles->unique('name') as $role)<span class="pill" style="color: {{ $role->color }}">{{ $role->label }}</span> @endforeach</td>
                    <td>
                        @if ($suspension = $user->activeSanction('suspension'))
                            <span style="color:var(--danger)" title="{{ $suspension->reason }}">{{ $suspension->expires_at ? __('suspended until :date', ['date' => $suspension->expires_at->shortDate()]) : __('suspended') }}</span>
                        @elseif ($user->isMuted())
                            <span style="color:var(--warn)">{{ __('muted') }}</span>
                        @elseif (! $user->hasVerifiedEmail())
                            <span class="muted">{{ __('unconfirmed email') }}</span>
                        @else
                            <span class="online-dot {{ $user->isOnline() ? 'on' : '' }}"></span>{{ __('active') }}
                        @endif
                    </td>
                    <td class="muted">{{ $user->last_seen_at?->diffForHumans() ?? '—' }}</td>
                    <td style="white-space:nowrap">
                        <button class="btn small" wire:click="select({{ $user->id }})">{{ __('Roles') }}</button>
                        <a class="btn small" href="{{ route('messages', ['to' => $user->name]) }}" wire:navigate title="{{ __('Send a message') }}">✉️</a>
                        @can('sanction', $user)
                            <button class="btn small danger" x-data x-on:click="$dispatch('open-sanction', { userId: {{ $user->id }} })">⚖️ {{ __('Sanction…') }}</button>
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <x-modal name="user-roles" :title="__('Roles')" width="520px">
        @if ($selected)
            <p style="margin-top:0">{{ __('Roles of') }} <x-user-name :user="$selected" /></p>
            <div class="stack">
                @forelse ($selected->roles as $role)
                    <div wire:key="ru{{ $role->pivot->id }}" style="display:flex;gap:8px;align-items:center">
                        <span class="pill" style="color: {{ $role->color }}">{{ $role->label }}</span>
                        <span class="muted" style="flex:1">{{ $role->pivot->scope_id ? __('section: :name', ['name' => $sections->firstWhere('id', $role->pivot->scope_id)?->name ?? '?']) : __('global') }}</span>
                        <button class="linkbtn" x-on:click="$confirm(@js(__('Remove this role?')), () => $wire.removeRole({{ $role->pivot->id }}), { title: @js(__('Are you sure?')), label: @js(__('Remove')) })">{{ __('remove') }}</button>
                    </div>
                @empty
                    <div class="muted">{{ __('No roles.') }}</div>
                @endforelse
            </div>
            <form wire:submit="addRole" style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <select wire:model.live="newRole" style="width:auto">
                    @foreach ($roles as $role)<option value="{{ $role->name }}">{{ $role->label }}</option>@endforeach
                </select>
                <select wire:model="newSectionId" style="width:auto">
                    <option value="">— {{ __('global') }} —</option>
                    @foreach ($sections as $s)<option value="{{ $s->id }}">{{ $s->name }}{{ $s->isPrivate() ? ' ('.__('private').')' : '' }}</option>@endforeach
                </select>
                <button class="btn small primary" type="submit">{{ __('Add role') }}</button>
            </form>
            @error('newSectionId')<div class="error">{{ __('Pick a section for this role.') }}</div>@enderror
        @endif
    </x-modal>

</div>
