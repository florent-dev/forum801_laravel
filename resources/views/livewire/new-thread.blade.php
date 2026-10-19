<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head"><h1>{{ __('New thread in :section', ['section' => $section->name]) }}</h1></div>
    <div class="scroll">
        <form class="form-box" wire:submit="save" x-data x-on:submit="forgetDraft(@js('new-'.$section->id))">
            <div class="field" style="display:flex;gap:8px;flex-wrap:wrap">
                <div style="width:160px">
                    <label for="prefix">{{ __('Prefix') }}</label>
                    <select id="prefix" wire:model="prefix">
                        <option value="">— {{ __('None') }}</option>
                        @foreach ($prefixes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div style="flex:1;min-width:220px">
                    <label for="title">{{ __('Title') }}</label>
                    <input id="title" type="text" wire:model="title" maxlength="120" required>
                    @error('title')<div class="error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="field">
                <label for="new-body">{{ __('Message') }}</label>
                <x-editor id="new-body" model="body" :preview="$preview" preview-toggle="togglePreview" rows="14" draft="new-{{ $section->id }}" :placeholder="__('Markdown supported. Ctrl+Enter to post.')" />
            </div>
            @if ($section->polls_enabled)
                @include('partials.poll-builder')
            @endif
            <button class="btn primary" type="submit" wire:loading.attr="disabled">{{ __('Post thread') }}</button>
            <a class="btn" href="{{ route('sections.show', $section) }}" wire:navigate>{{ __('Cancel') }}</a>
        </form>
    </div>
</div>
