<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head">
        <h1 class="with-art"><x-art name="guilds" /> {{ __('Guilds') }}</h1>
        <div style="display:flex;gap:6px;align-items:center">
            {{ $guilds->links() }}
            @foreach ($myGuilds as $mine)
                <a class="btn small" href="{{ route('guilds.show', $mine) }}" wire:navigate>🏰 {{ $mine->name }}</a>
            @endforeach
            @can('create', \App\Models\Guild::class)
                <button type="button" class="btn small primary" x-data x-on:click="$dispatch('open-modal', 'create-guild')">＋ {{ __('Create a guild') }}</button>
            @endcan
        </div>
    </div>
    <div class="scroll">
        <div class="search-summary">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Name or tag…') }}" style="width:220px">
            <select wire:model.live="community" style="width:auto">
                <option value="all">{{ __('All communities') }}</option>
                @foreach (\App\Support\Community::all() as $code => $c)<option value="{{ $code }}">{{ $c['label'] }}</option>@endforeach
            </select>
            <label class="switch"><input type="checkbox" wire:model.live="recruiting"><span class="slider"></span><span class="switch-label">{{ __('Recruiting only') }}</span></label>
        </div>
        <div class="guild-grid">
            @forelse ($guilds as $guild)
                <a class="guild-card" href="{{ route('guilds.show', $guild) }}" wire:navigate wire:key="g{{ $guild->id }}">
                    <img src="{{ $guild->emblemUrl() }}" alt="">
                    <div class="guild-card-body">
                        <div class="guild-card-name">{{ $guild->name }} <span class="guild-tag">[{{ $guild->tag }}]</span></div>
                        <div class="meta">
                            <img class="flag" src="{{ $guild->flagUrl() }}" alt="">
                            {{ trans_choice(':count member|:count members', $guild->members_count) }}
                        </div>
                        <span class="recruit recruit-{{ $guild->recruitment }}">{{ ['open' => __('Open to everyone'), 'application' => __('Recruiting on application'), 'closed' => __('Not recruiting')][$guild->recruitment] }}</span>
                    </div>
                </a>
            @empty
                <div class="empty">{{ __('No guild found.') }}</div>
            @endforelse
        </div>
    </div>

    @can('create', \App\Models\Guild::class)
        <x-modal name="create-guild" :title="__('Create a guild')" width="560px">
            <form wire:submit="create">
                <div style="display:grid;grid-template-columns:1fr 120px;gap:10px">
                    <div class="field"><label for="g-name">{{ __('Name') }}</label><input id="g-name" type="text" wire:model="name" maxlength="40">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="field"><label for="g-tag">{{ __('Tag') }}</label><input id="g-tag" type="text" wire:model="tag" maxlength="6" placeholder="MICE">@error('tag')<div class="error">{{ $message }}</div>@enderror</div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div class="field"><label for="g-community">{{ __('Community') }}</label>
                        <select id="g-community" wire:model="newCommunity">@foreach (\App\Support\Community::all() as $code => $c)<option value="{{ $code }}">{{ $c['label'] }}</option>@endforeach</select></div>
                    <div class="field"><label for="g-recruit">{{ __('Recruitment') }}</label>
                        <select id="g-recruit" wire:model="recruitment">
                            <option value="open">{{ __('Open to everyone') }}</option>
                            <option value="application">{{ __('On application') }}</option>
                            <option value="closed">{{ __('Closed') }}</option>
                        </select></div>
                </div>
                <div class="field"><label for="g-desc">{{ __('Presentation (Markdown, shown on the public page)') }}</label><textarea id="g-desc" wire:model="description" rows="5"></textarea>@error('description')<div class="error">{{ $message }}</div>@enderror</div>
                <p class="muted" style="font-size:11px">{{ __('Your guild gets its own forum section. You become its owner and can name officers to keep it tidy.') }}</p>
                <div class="modal-actions">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'create-guild')">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn primary">🏰 {{ __('Create') }}</button>
                </div>
            </form>
        </x-modal>
    @endcan
</div>
