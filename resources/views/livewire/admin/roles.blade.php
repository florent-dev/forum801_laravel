<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head"><h1>{{ __('Roles') }}</h1></div>
    <div class="scroll">
        <table class="grid">
            <thead><tr><th>{{ __('Role') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Label') }}</th><th>{{ __('Name color') }}</th><th>{{ __('Assignments') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($roles as $role)
                <tr wire:key="role{{ $role->id }}">
                    <td><code>{{ $role->name }}</code></td>
                    <td class="muted">{{ __(str_replace('_', ' ', $role->scope)) }}</td>
                    <td><input type="text" wire:model="labels.{{ $role->id }}">@error("labels.$role->id")<div class="error">{{ $message }}</div>@enderror</td>
                    <td><input type="color" wire:model.live="colors.{{ $role->id }}"> <span style="color: {{ $colors[$role->id] ?? $role->color }}">{{ __('Sample') }}</span>@error("colors.$role->id")<div class="error">{{ $message }}</div>@enderror</td>
                    <td>{{ $role->users_count }}</td>
                    <td><button class="btn small primary" wire:click="save({{ $role->id }})">{{ __('Save') }}</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="muted" style="font-size:11px;padding:8px">{{ __('Permissions per role are defined in code (policies). Assign roles from the Users page.') }}</p>
    </div>
</div>
