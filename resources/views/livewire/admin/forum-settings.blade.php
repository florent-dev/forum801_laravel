<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head"><h1 class="with-art"><x-art name="toggles" /> {{ __('Forum settings') }}</h1></div>
    <form class="scroll settings-page" wire:submit="save">
        <div class="settings-card">
            <h3>{{ __('Registration') }}</h3>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('New accounts') }}</strong><span>{{ __('When closed, the register buttons disappear and nobody can create an account.') }}</span></div>
                <div class="settings-field"><label class="switch"><input type="checkbox" wire:model="registrationOpen"><span class="slider"></span><span class="switch-label">{{ __('Registration open') }}</span></label></div>
            </div>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Captcha') }}</strong><span>{{ __('Ask for the characters of an image when creating an account.') }}</span></div>
                <div class="settings-field"><label class="switch"><input type="checkbox" wire:model="captcha"><span class="slider"></span><span class="switch-label">{{ __('Captcha on registration') }}</span></label></div>
            </div>
        </div>

        <div class="settings-card">
            <h3>{{ __('Posting limits') }}</h3>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Posts per minute') }}</strong><span>{{ __('New threads and replies a user may send in one minute.') }}</span></div>
                <div class="settings-field"><input type="number" min="1" max="120" wire:model="postsPerMinute" class="input-num">@error('postsPerMinute')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Private messages per minute') }}</strong></div>
                <div class="settings-field"><input type="number" min="1" max="120" wire:model="messagesPerMinute" class="input-num">@error('messagesPerMinute')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Maximum post length') }}</strong><span>{{ __('In characters, for posts and messages.') }}</span></div>
                <div class="settings-field"><input type="number" min="100" max="100000" step="100" wire:model="maxPostLength" class="input-num">@error('maxPostLength')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Edit time window') }}</strong><span>{{ __('Minutes during which authors can edit their posts. Leave empty for no limit. Moderators can always edit.') }}</span></div>
                <div class="settings-field"><input type="number" min="1" wire:model="editWindow" class="input-num" placeholder="{{ __('No limit') }}">@error('editWindow')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
        </div>

        <div class="settings-card">
            <h3>{{ __('Guilds') }}</h3>
            <div class="settings-row">
                <div class="settings-label"><strong>{{ __('Guilds per user') }}</strong><span>{{ __('How many guilds a user can belong to at the same time.') }}</span></div>
                <div class="settings-field"><input type="number" min="1" max="50" wire:model="maxGuilds" class="input-num">@error('maxGuilds')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
            <div class="settings-actions"><button type="submit" class="btn primary">{{ __('Save') }}</button></div>
        </div>
    </form>
</div>
