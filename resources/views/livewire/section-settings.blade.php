<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head"><h1>{{ $section->name }} · {{ __('settings') }}</h1></div>
    <div class="scroll">
        <form class="form-box" wire:submit="save">
            <h3>{{ __('Section') }}</h3>
            <div class="field"><label>{{ __('Description') }}</label><input type="text" wire:model="description" style="width:100%">@error('description')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label>{{ __('Icon') }}</label><select wire:model.live="icon">@foreach ($icons as $i)<option value="{{ $i }}">{{ basename($i) }}</option>@endforeach</select>
                @if ($icon)<img src="{{ asset($icon) }}" alt="" style="vertical-align:middle;width:32px">@endif</div>
            <div class="field"><label>{{ __('Community') }}</label><div><img class="flag" src="{{ $section->flagUrl() }}" alt=""> {{ $section->communityLabel() }} <span class="muted">({{ __('changed by administrators') }})</span></div></div>
            <button class="btn primary" type="submit">{{ __('Save') }}</button>
        </form>
        <div class="form-box">
            <h3>{{ __('Section staff') }}</h3>
            @forelse ($staff as $row)
                <div>
                    <a href="{{ route('profile', $row->name) }}" style="color: {{ $row->color }}">{{ $row->name }}</a>
                    <span class="pill" style="color: {{ $row->color }}">{{ $row->label }}</span>
                    @if (in_array($row->role, ['moderator', 'private_moderator']))
                        <button class="linkbtn" x-data x-on:click="$confirm(@js(__(':name will no longer moderate this section.', ['name' => $row->name])), () => $wire.removeModerator({{ $row->id }}), { title: @js(__('Remove moderator?')), label: @js(__('Remove')) })">{{ __('remove') }}</button>
                    @endif
                </div>
            @empty
                <div class="muted">{{ __('No section-specific staff.') }}</div>
            @endforelse
            <form wire:submit="addModerator" style="margin-top:8px;display:flex;gap:6px">
                <input type="text" wire:model="moderatorName" placeholder="{{ __('Nickname') }}">
                <button class="btn small primary" type="submit">{{ __('Add moderator') }}</button>
            </form>
            @error('moderatorName')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
