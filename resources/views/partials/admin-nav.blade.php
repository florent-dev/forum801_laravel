<div class="panel-head"><h2>{{ __('Administration') }}</h2></div>
<div class="scroll">
    <a class="ui-content settings-nav {{ request()->routeIs('admin.users') ? 'selected' : '' }}" href="{{ route('admin.users') }}" wire:navigate>
        <x-art name="users" size="26" class="settings-icon" />
        <span><span class="title">{{ __('Users & roles') }}</span><span class="meta">{{ __('Ban, assign global or section roles') }}</span></span>
    </a>
    <a class="ui-content settings-nav {{ request()->routeIs('admin.settings') ? 'selected' : '' }}" href="{{ route('admin.settings') }}" wire:navigate>
        <x-art name="toggles" size="26" class="settings-icon" />
        <span><span class="title">{{ __('Forum settings') }}</span><span class="meta">{{ __('Registration, captcha, limits, edit window') }}</span></span>
    </a>
    <a class="ui-content settings-nav {{ request()->routeIs('admin.sections') ? 'selected' : '' }}" href="{{ route('admin.sections') }}" wire:navigate>
        <x-art name="sections" size="26" class="settings-icon" />
        <span><span class="title">{{ __('Sections') }}</span><span class="meta">{{ __('Create, edit, order, visibility') }}</span></span>
    </a>
    <a class="ui-content settings-nav {{ request()->routeIs('admin.roles') ? 'selected' : '' }}" href="{{ route('admin.roles') }}" wire:navigate>
        <x-art name="roles" size="26" class="settings-icon" />
        <span><span class="title">{{ __('Roles') }}</span><span class="meta">{{ __('Labels and name colors') }}</span></span>
    </a>
    <a class="ui-content settings-nav {{ request()->routeIs('admin.words') ? 'selected' : '' }}" href="{{ route('admin.words') }}" wire:navigate>
        <x-art name="filter" size="26" class="settings-icon" />
        <span><span class="title">{{ __('Word filter') }}</span><span class="meta">{{ __('Block, mask or report words') }}</span></span>
    </a>
    <a class="ui-content settings-nav {{ request()->routeIs('admin.security') ? 'selected' : '' }}" href="{{ route('admin.security') }}" wire:navigate>
        <x-art name="security" size="26" class="settings-icon" />
        <span><span class="title">{{ __('Security') }}</span><span class="meta">{{ __('Events, IP lookup, suspicious activity, IP bans') }}</span></span>
    </a>
    <a class="ui-content settings-nav {{ request()->routeIs('admin.maintenance') ? 'selected' : '' }}" href="{{ route('admin.maintenance') }}" wire:navigate>
        <x-art name="maintenance" size="26" class="settings-icon" />
        <span><span class="title">{{ __('Maintenance') }}</span><span class="meta">{{ __('Close the forum, announce a maintenance') }}</span></span>
    </a>
    <a class="ui-content settings-nav {{ request()->routeIs('admin.health') ? 'selected' : '' }}" href="{{ route('admin.health') }}" wire:navigate>
        <x-art name="health" size="26" class="settings-icon" />
        <span><span class="title">{{ __('System health') }}</span><span class="meta">{{ __('Versions, database, cache, mail, scheduler') }}</span></span>
    </a>
    <a class="ui-content settings-nav {{ request()->routeIs('admin.privacy') ? 'selected' : '' }}" href="{{ route('admin.privacy') }}" wire:navigate>
        <x-art name="data" size="26" class="settings-icon" />
        <span><span class="title">{{ __('Personal data') }}</span><span class="meta">{{ __('Export or anonymize an account') }}</span></span>
    </a>
    <a class="ui-content settings-nav " href="{{ route('moderation') }}" wire:navigate>
        <x-art name="reports" size="26" class="settings-icon" />
        <span><span class="title">{{ __('Moderation queue') }}</span><span class="meta">{{ __('Reports and logs') }}</span></span>
    </a>
</div>
