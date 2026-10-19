@props(['name', 'title' => null, 'width' => '480px'])
{{-- Generic modal: open with $dispatch('open-modal', 'name'), close with 'close-modal' --}}
<div x-data="{ show: false, name: @js($name) }"
     x-on:open-modal.window="if ($event.detail === name || $event.detail?.name === name) { show = true; $nextTick(() => $el.querySelector('[autofocus], input:not([type=hidden]), textarea, select')?.focus()) }"
     x-on:close-modal.window="if (! $event.detail || $event.detail === name || $event.detail?.name === name) show = false"
     x-on:keydown.escape.window="show = false"
     x-show="show" x-cloak x-transition.opacity.duration.150ms
     class="modal-backdrop" {{ $attributes }}>
    <div class="modal" style="max-width: {{ $width }}" x-on:click.outside="show = false" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
        <div class="modal-head">
            <h3>{{ $title }}</h3>
            <button type="button" class="modal-close" x-on:click="show = false" aria-label="{{ __('Close') }}">&times;</button>
        </div>
        <div class="modal-body">{{ $slot }}</div>
    </div>
</div>
