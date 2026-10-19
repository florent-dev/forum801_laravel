<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head"><h1 class="with-art"><x-art name="maintenance" /> {{ __('Maintenance') }}</h1></div>
    <div class="scroll settings-page">
        <div class="settings-card maintenance-status {{ $state['enabled'] ? 'on' : 'off' }}">
            <div class="maintenance-light"></div>
            <div style="flex:1">
                <strong>{{ $state['enabled'] ? __('Maintenance mode is ON') : __('The forum is open') }}</strong>
                <div class="muted">
                    @if ($state['enabled'])
                        {{ __('Since :when, by :name.', ['when' => \Illuminate\Support\Carbon::parse($state['since'] ?? now())->diffForHumans(), 'name' => $state['by'] ?? __('the server')]) }}
                        {{ $state['allow_moderators'] ? __('Administrators and moderators can still use the forum.') : __('Only administrators can still use the forum.') }}
                    @else
                        {{ __('Everybody can use the forum.') }}
                    @endif
                </div>
            </div>
            @if ($state['enabled'])
                <button type="button" class="btn primary" wire:click="disable">✅ {{ __('Reopen the forum') }}</button>
            @endif
        </div>

        @if ($accessUrl)
            <div class="settings-card">
                <h3>🔑 {{ __('Secret staff link') }}</h3>
                <p class="muted" style="font-size:12px">{{ __('During maintenance the login page is hidden. Open this link to log in; share it only with the team.') }}</p>
                <div class="secret-link" x-data>
                    <code x-ref="url">{{ $accessUrl }}</code>
                    <button type="button" class="btn small" x-on:click="navigator.clipboard?.writeText($refs.url.innerText); $dispatch('toast', { message: @js(__('Link copied to clipboard.')), type: 'info' })">{{ __('Copy') }}</button>
                    <button type="button" class="btn small" x-on:click="$confirm(@js(__('The current link will stop working.')), () => $wire.regenerateSecret(), { title: @js(__('Create a new link?')), label: @js(__('Regenerate')), danger: false })">{{ __('Regenerate') }}</button>
                </div>
            </div>
        @endif

        <form class="settings-card" wire:submit="enable">
            <h3>{{ __('Close the forum for maintenance') }}</h3>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Message to visitors') }}</strong><span>{{ __('Shown on the maintenance page and in the announcement banner.') }}</span></div>
                <div class="settings-field"><textarea wire:model="message" rows="3" maxlength="1000" placeholder="{{ __('We are upgrading the servers, see you in an hour!') }}"></textarea>@error('message')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Expected end') }}</strong><span>{{ __('Optional, shown to visitors and sent as Retry-After.') }}</span></div>
                <div class="settings-field"><input type="datetime-local" wire:model="until">@error('until')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Who can still enter') }}</strong></div>
                <div class="settings-field"><label class="switch"><input type="checkbox" wire:model="allowModerators"><span class="slider"></span><span class="switch-label">{{ __('Let moderators in too (administrators always can)') }}</span></label></div>
            </div>
            <div class="settings-actions">
                <button type="button" class="btn danger" x-data x-on:click="$confirm(@js(__('Visitors and members will see the maintenance page until you reopen the forum.')), () => $wire.enable(), { title: @js(__('Start maintenance now?')), label: @js(__('Start maintenance')) })">🛠️ {{ $state['enabled'] ? __('Update maintenance') : __('Start maintenance') }}</button>
            </div>
        </form>

        <form class="settings-card" wire:submit="schedule">
            <h3>📣 {{ __('Announce an upcoming maintenance') }}</h3>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Planned start') }}</strong><span>{{ __('Everybody sees a banner until it starts. Leave empty to remove the announcement.') }}</span></div>
                <div class="settings-field"><input type="datetime-local" wire:model="scheduledAt">@error('scheduledAt')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
            <div class="settings-actions"><button type="submit" class="btn primary">{{ __('Save the announcement') }}</button></div>
        </form>

        <div class="settings-card">
            <h3>🖥️ {{ __('From the server') }}</h3>
            <pre class="revision-body">php artisan forum:maintenance on --message="…" --until="2026-10-20 22:00" --allow-moderators
php artisan forum:maintenance off
php artisan forum:maintenance status
php artisan down --secret=…   # {{ __('full Laravel maintenance, same page') }}</pre>
        </div>
    </div>
</div>
