<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head"><h1 class="with-art"><x-art name="data" /> {{ __('Personal data') }}</h1></div>
    <div class="scroll settings-page">
        <div class="settings-card">
            <h3>{{ __('Find a user') }}</h3>
            <p class="muted" style="font-size:12px">{{ __('Answer access and erasure requests: download everything the forum stores about a user, or anonymize the account.') }}</p>
            <div class="field"><input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Nickname, email or id') }}" aria-label="{{ __('Find a user') }}"></div>
            @foreach ($results as $result)
                <div class="data-user" wire:key="du{{ $result->id }}">
                    <x-user-name :user="$result" />
                    <span class="muted" style="font-size:11px">#{{ $result->id }} · {{ $result->email }}</span>
                    <button type="button" class="btn small {{ $picked?->id === $result->id ? 'primary' : '' }}" style="margin-left:auto" wire:click="pick({{ $result->id }})">{{ __('Select') }}</button>
                </div>
            @endforeach
            @if (trim($search) !== '' && $results->isEmpty())<div class="empty">{{ __('Nobody matches.') }}</div>@endif
        </div>

        @if ($picked)
            <div class="settings-card">
                <h3>{{ $picked->name }} <span class="muted">#{{ $picked->id }}</span></h3>
                <div class="health-grid">
                    <div class="health-tile"><div class="k">{{ __('Email') }}</div><div class="v">{{ $picked->email }}</div></div>
                    <div class="health-tile"><div class="k">{{ __('Registered') }}</div><div class="v">{{ $picked->created_at?->shortDate() }}</div></div>
                    <div class="health-tile"><div class="k">{{ __('Threads') }}</div><div class="v">{{ $picked->threads_count }}</div></div>
                    <div class="health-tile"><div class="k">{{ __('Posts') }}</div><div class="v">{{ $picked->posts_count }}</div></div>
                </div>
                <div class="settings-row">
                    <div class="settings-label"><strong>{{ __('Export') }}</strong><span>{{ __('A JSON file with the account, posts, messages, votes, friends, guilds, sanctions, IP addresses and notifications.') }}</span></div>
                    <div class="settings-field"><a class="btn" href="{{ route('admin.privacy.export', $picked) }}">⬇️ {{ __('Download the data') }}</a></div>
                </div>
                @if ($picked->status === 'deleted')
                    <div class="settings-row"><div class="settings-label"><strong>{{ __('Anonymize') }}</strong></div><div class="settings-field muted">{{ __('This account is already anonymized.') }}</div></div>
                @elseif ($picked->hasRole(\App\Models\Role::ADMINISTRATOR) || $picked->id === auth()->id())
                    <div class="settings-row"><div class="settings-label"><strong>{{ __('Anonymize') }}</strong></div><div class="settings-field muted">{{ __('Remove the administrator role first.') }}</div></div>
                @else
                    <form class="settings-row" wire:submit="anonymize">
                        <div class="settings-label"><strong>{{ __('Anonymize') }}</strong><span>{{ __('Removes the nickname, email, password, profile, IP addresses, friends, guilds and notifications. Posts stay, signed “deleted-:id”. This cannot be undone.', ['id' => $picked->id]) }}</span></div>
                        <div class="settings-field" style="display:grid;gap:8px">
                            <label class="switch"><input type="checkbox" wire:model="deletePosts"><span class="slider"></span><span class="switch-label">{{ __('Also delete their posts') }}</span></label>
                            <input type="text" wire:model="confirmName" placeholder="{{ __('Type :name to confirm', ['name' => $picked->name]) }}" autocomplete="off">
                            @error('confirmName')<div class="error">{{ $message }}</div>@enderror
                            <div><button type="submit" class="btn danger">{{ __('Anonymize the account') }}</button></div>
                        </div>
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>
