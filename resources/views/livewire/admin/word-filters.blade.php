@php($actions = ['block' => __('Block the post'), 'replace' => __('Replace with ***'), 'review' => __('Publish and report')])
<div style="display:flex;flex-direction:column;min-height:0;flex:1">
    <div class="panel-head"><h1 class="with-art"><x-art name="filter" /> {{ __('Word filter') }}</h1></div>
    <div class="scroll settings-page">
        <form class="settings-card" wire:submit="add">
            <h3>{{ __('Add a word or phrase') }}</h3>
            <p class="muted" style="font-size:12px">{{ __('Applies to thread titles, posts and private messages. Case is ignored. Private messages cannot be reported, so “Publish and report” lets them through.') }}</p>
            <div class="filter-add">
                <input type="text" wire:model="phrase" maxlength="100" placeholder="{{ __('Word or phrase') }}" aria-label="{{ __('Word or phrase') }}">
                <select wire:model="action" aria-label="{{ __('Action') }}">@foreach ($actions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                <label class="switch"><input type="checkbox" wire:model="wholeWord"><span class="slider"></span><span class="switch-label">{{ __('Whole word only') }}</span></label>
                <button type="submit" class="btn primary">{{ __('Add') }}</button>
            </div>
            @error('phrase')<div class="error">{{ $message }}</div>@enderror
        </form>

        <div class="settings-card">
            <h3>{{ trans_choice(':count filtered word|:count filtered words', $filters->count(), ['count' => $filters->count()]) }}</h3>
            @forelse ($filters as $filter)
                <div class="filter-row" wire:key="wf{{ $filter->id }}">
                    <code class="filter-phrase">{{ $filter->phrase }}</code>
                    <select class="select-sm" wire:change="setAction({{ $filter->id }}, $event.target.value)" aria-label="{{ __('Action') }}">
                        @foreach ($actions as $key => $label)<option value="{{ $key }}" @selected($filter->action === $key)>{{ $label }}</option>@endforeach
                    </select>
                    <label class="switch" title="{{ __('Whole word only') }}"><input type="checkbox" @checked($filter->whole_word) wire:click="toggleWholeWord({{ $filter->id }})"><span class="slider"></span><span class="switch-label">{{ __('Whole word') }}</span></label>
                    <span class="muted filter-by">{{ $filter->creator?->name }} · {{ $filter->created_at->shortDate() }}</span>
                    <button type="button" class="linkbtn danger" wire:click="remove({{ $filter->id }})">{{ __('Remove') }}</button>
                </div>
            @empty
                <div class="empty">{{ __('No filtered words yet.') }}</div>
            @endforelse
        </div>

        <form class="settings-card" wire:submit="test">
            <h3>{{ __('Try the filter') }}</h3>
            <div class="field"><textarea wire:model="sample" rows="3" maxlength="5000" placeholder="{{ __('Type a text to see what the filter does with it.') }}"></textarea>@error('sample')<div class="error">{{ $message }}</div>@enderror</div>
            @if ($result)
                <div class="filter-result">
                    @if ($result['blocked'])
                        <div class="error">⛔ {{ $result['blocked'] }}</div>
                    @else
                        <div>{{ __('Published as:') }} <span class="filter-out">{{ $result['text'] }}</span></div>
                        @if ($result['review'])<div class="muted">🚩 {{ __('Sent to the moderation queue for: :words', ['words' => implode(', ', $result['review'])]) }}</div>@endif
                    @endif
                </div>
            @endif
            <div class="settings-actions"><button type="submit" class="btn">{{ __('Test') }}</button></div>
        </form>
    </div>
</div>
