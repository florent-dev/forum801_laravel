<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head">
        <div class="tabs">
            <a href="#" class="{{ $tab === 'members' ? 'active' : '' }}" wire:click.prevent="$set('tab', 'members')">👥 {{ __('Members') }} ({{ $members->count() }})</a>
            <a href="#" class="{{ $tab === 'applications' ? 'active' : '' }}" wire:click.prevent="$set('tab', 'applications')">✉️ {{ __('Applications') }} @if ($applications->count())<span class="badge">{{ $applications->count() }}</span>@endif</a>
            @if ($isOwner)<a href="#" class="{{ $tab === 'page' ? 'active' : '' }}" wire:click.prevent="$set('tab', 'page')">🏰 {{ __('Public page & settings') }}</a>@endif
        </div>
        <a class="btn small" href="{{ route('guilds.show', $guild) }}" wire:navigate>← {{ __('Guild page') }}</a>
    </div>
    <div class="scroll settings-page">
        @if ($tab === 'members')
            <table class="grid">
                <thead><tr><th>{{ __('User') }}</th><th>{{ __('Role') }}</th><th>{{ __('Joined') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($members as $member)
                    <tr wire:key="gm{{ $member->id }}">
                        <td><x-user-name :user="$member->user" /></td>
                        <td><span class="pill guild-role-{{ $member->role }}">{{ __(ucfirst($member->role)) }}</span></td>
                        <td class="muted">{{ $member->created_at->diffForHumans() }}</td>
                        <td style="white-space:nowrap;text-align:right">
                            @if ($member->role !== 'owner')
                                @if ($isOwner)
                                    @if ($member->role === 'member')
                                        <button type="button" class="btn small" wire:click="setRole({{ $member->id }}, 'officer')">⬆ {{ __('Make officer') }}</button>
                                    @else
                                        <button type="button" class="btn small" wire:click="setRole({{ $member->id }}, 'member')">⬇ {{ __('Make member') }}</button>
                                    @endif
                                    <button type="button" class="btn small" x-data x-on:click="$confirm(@js(__(':name becomes the owner and you become an officer.', ['name' => $member->user->name])), () => $wire.transfer({{ $member->id }}), { title: @js(__('Transfer ownership?')), label: @js(__('Transfer')) })">👑 {{ __('Transfer') }}</button>
                                @endif
                                @if ($isOwner || $member->role === 'member')
                                    <button type="button" class="btn small danger" x-data x-on:click="$confirm(@js(__('Remove :name from the guild?', ['name' => $member->user->name])), () => $wire.kick({{ $member->id }}), { label: @js(__('Remove')) })">✕</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="muted" style="font-size:11px;padding:8px 5px">{{ __('Officers can moderate the guild forum (lock, pin, move, hide or delete posts and threads) and handle applications. They are not official forum moderators.') }}</p>

        @elseif ($tab === 'applications')
            @forelse ($applications as $application)
                <div class="settings-card" wire:key="ga{{ $application->id }}" style="max-width:none">
                    <div style="display:flex;gap:10px;align-items:center;padding-top:10px">
                        <img src="{{ $application->user->avatar() }}" alt="" style="width:36px;height:36px;border-radius:8px">
                        <div style="flex:1"><x-user-name :user="$application->user" /> <span class="muted">· {{ $application->created_at->diffForHumans() }}</span></div>
                        <button type="button" class="btn small primary" wire:click="decide({{ $application->id }}, true)">✓ {{ __('Accept') }}</button>
                        <button type="button" class="btn small" wire:click="decide({{ $application->id }}, false)">✕ {{ __('Decline') }}</button>
                    </div>
                    @if ($application->message)<div class="preview" style="white-space:pre-line">{{ $application->message }}</div>@endif
                </div>
            @empty
                <div class="empty">{{ __('No application waiting.') }}</div>
            @endforelse
            @error('application')<div class="flash" style="background:#4a1622;color:#ffc2cf">{{ $message }}</div>@enderror

        @elseif ($isOwner)
            <form class="settings-card" wire:submit="savePage">
                <div class="settings-row">
                    <div class="settings-label"><strong>{{ __('Tag') }}</strong><span>{{ __('2 to 6 letters or digits.') }}</span></div>
                    <div class="settings-field"><input type="text" wire:model="tag" maxlength="6">@error('tag')<div class="error">{{ $message }}</div>@enderror</div>
                </div>
                <div class="settings-row">
                    <div class="settings-label"><strong>{{ __('Emblem') }}</strong><span>{{ __('Pick an icon or paste an https image address.') }}</span></div>
                    <div class="settings-field avatar-field">
                        <img class="avatar-preview" src="{{ $emblem ? (str_starts_with($emblem, 'http') ? $emblem : asset($emblem)) : asset('img/sections/Tribes.png') }}" alt="" style="object-fit:contain">
                        <div style="flex:1">
                            <select wire:model.live="emblem"><option value="">{{ __('Default') }}</option>@foreach ($icons as $icon)<option value="{{ $icon }}">{{ basename($icon, '.png') }}</option>@endforeach</select>
                            <input type="text" wire:model.blur="emblem" placeholder="https://…" style="margin-top:6px">
                            @error('emblem')<div class="error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-label"><strong>{{ __('Recruitment') }}</strong></div>
                    <div class="settings-field option-cards">
                        @foreach (['open' => __('Open to everyone'), 'application' => __('On application'), 'closed' => __('Closed')] as $value => $label)
                            <label class="option-card"><input type="radio" wire:model="recruitment" value="{{ $value }}"> {{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-label"><strong>{{ __('Guild forum') }}</strong><span>{{ __('Members can always read and post. Make it readable by everyone if you want to show off.') }}</span></div>
                    <div class="settings-field"><label class="switch"><input type="checkbox" wire:model="forumPublic"><span class="slider"></span><span class="switch-label">{{ __('Everyone can read the guild forum') }}</span></label></div>
                </div>
                <div class="settings-row">
                    <div class="settings-label"><strong>{{ __('Presentation') }}</strong><span>{{ __('Markdown, shown on the public page.') }}</span></div>
                    <div class="settings-field"><x-editor id="guild-desc" model="description" rows="8" /></div>
                </div>
                <div class="settings-actions"><button type="submit" class="btn primary">{{ __('Save') }}</button></div>
            </form>
            <div class="settings-card" style="border:1px solid var(--danger)">
                <h3 style="color:var(--danger)">⚠️ {{ __('Danger zone') }}</h3>
                <p class="muted" style="font-size:11px">{{ __('Disbanding removes every member. The forum is closed and kept for the moderators.') }}</p>
                <button type="button" class="btn danger" x-data x-on:click="$confirm(@js(__('Disband :name for good?', ['name' => $guild->name])), () => $wire.deleteGuild(), { title: @js(__('Disband the guild')), label: @js(__('Disband')) })">{{ __('Disband the guild') }}</button>
            </div>
        @endif
    </div>
</div>
