<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head"><h1>{{ __('Sections') }}</h1></div>
    <div class="scroll">
        <form class="form-box" wire:submit="save">
            <h3>{{ $editingId ? __('Edit section') : __('New section') }}</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px">
                <div class="field"><label>{{ __('Name') }}</label><input type="text" wire:model="form.name">@error('form.name')<div class="error">{{ $message }}</div>@enderror</div>
                <div class="field"><label>{{ __('Slug') }}</label><input type="text" wire:model="form.slug" placeholder="{{ __('auto') }}">@error('form.slug')<div class="error">{{ $message }}</div>@enderror</div>
                <div class="field"><label>{{ __('Visibility') }}</label><select wire:model="form.visibility"><option value="public">{{ __('Public') }}</option><option value="private">{{ __('Private') }}</option></select></div>
                <div class="field"><label>{{ __('Position') }}</label><input type="text" wire:model="form.position">@error('form.position')<div class="error">{{ $message }}</div>@enderror</div>
                <div class="field"><label>{{ __('Icon') }}</label><select wire:model.live="form.icon">@foreach ($icons as $i)<option value="{{ $i }}">{{ basename($i) }}</option>@endforeach</select></div>
                <div class="field"><label>{{ __('Community') }}</label><select wire:model="form.community">@foreach ($communities as $code => $c)<option value="{{ $code }}">{{ $c['label'] }}</option>@endforeach</select>@error('form.community')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
            <div class="field"><label>{{ __('Description') }}</label><input type="text" wire:model="form.description" style="width:100%"></div>
            @if (!empty($form['icon']))<img src="{{ asset($form['icon']) }}" alt="" style="vertical-align:middle">@endif
            <button class="btn primary" type="submit">{{ $editingId ? __('Save') : __('Create') }}</button>
            @if ($editingId)<button class="btn" type="button" wire:click="cancel">{{ __('Cancel') }}</button>@endif
        </form>
        <table class="grid">
            <thead><tr><th></th><th></th><th>{{ __('Name') }}</th><th>{{ __('Slug') }}</th><th>{{ __('Visibility') }}</th><th>{{ __('Pos.') }}</th><th>{{ __('Threads') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($sections as $s)
                <tr wire:key="s{{ $s->id }}">
                    <td><img class="flag" src="{{ $s->flagUrl() }}" alt="{{ $s->community }}" title="{{ $s->communityLabel() }}"></td>
                    <td><img src="{{ $s->iconUrl() }}" alt="" style="width:24px"></td>
                    <td><a href="{{ route('sections.show', $s) }}" wire:navigate>{{ $s->name }}</a></td>
                    <td class="muted">{{ $s->slug }}</td>
                    <td>{{ __(ucfirst($s->visibility)) }}</td>
                    <td>{{ $s->position }}</td>
                    <td>{{ $s->threads_count }}</td>
                    <td style="white-space:nowrap">
                        <button class="btn small" wire:click="edit({{ $s->id }})">{{ __('Edit') }}</button>
                        <a class="btn small" href="{{ route('sections.settings', $s) }}" wire:navigate>{{ __('Moderators') }}</a>
                        <button class="btn small danger" x-data x-on:click="$confirm(@js(__('This deletes :name and all of its :count threads. This cannot be undone.', ['name' => $s->name, 'count' => $s->threads_count])), () => $wire.delete({{ $s->id }}), { title: @js(__('Delete section?')), label: @js(__('Delete')) })">{{ __('Delete') }}</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
