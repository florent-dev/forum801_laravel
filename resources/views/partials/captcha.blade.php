{{-- Registration captcha + honeypot. $model: Livewire property name, or null for a classic form field --}}
@if (config('forum.captcha'))
    <div class="field captcha-field" x-data="{ src: @js(route('captcha')) + '?t=' + Date.now(), refresh() { this.src = @js(route('captcha')) + '?t=' + Date.now() } }"
         x-on:captcha-refresh.window="refresh()" x-on:open-modal.window="if ($event.detail?.tab === 'register' || $event.detail?.name === 'auth') refresh()">
        <label for="{{ $id ?? 'captcha' }}">{{ __('Security check') }}</label>
        <div class="captcha-row">
            <img :src="src" width="180" height="56" alt="{{ __('Type the characters shown in the image') }}">
            <button type="button" class="btn small" x-on:click="refresh()" title="{{ __('New image') }}">↻ {{ __('New image') }}</button>
        </div>
        <input id="{{ $id ?? 'captcha' }}" type="text" @if ($model ?? null) wire:model="{{ $model }}" @else name="captcha" @endif
               autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="8" required
               placeholder="{{ __('Type the characters shown in the image') }}">
        @error($model ?? 'captcha')<div class="error">{{ $message }}</div>@enderror
    </div>
@endif
{{-- Honeypot field, invisible to people --}}
<div class="hp-field" aria-hidden="true">
    <label>Website <input type="text" tabindex="-1" autocomplete="off" @if ($model ?? null) wire:model="website" @else name="website" @endif></label>
</div>
