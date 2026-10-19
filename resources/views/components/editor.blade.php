@props(['id', 'model', 'preview' => null, 'draft' => null, 'previewToggle' => null, 'max' => null])
@php
    $max ??= \App\Support\ForumSettings::maxPostLength();
    // Toolbar groups: [icon, title with shortcut, markdown before, after, placeholder]
    $groups = [
        [
            ['bold', __('Bold').' (Ctrl+B)', '**', '**', __('bold')],
            ['italic', __('Italic').' (Ctrl+I)', '*', '*', __('italic')],
            ['strike', __('Strikethrough'), '~~', '~~', __('text')],
            ['code', __('Code'), '`', '`', __('code')],
            ['eye-off', __('Spoiler'), '[spoiler]', '[/spoiler]', __('hidden text')],
        ],
        [
            ['heading', __('Heading'), "\n## ", "\n", __('Title')],
            ['quote', __('Quote'), "\n> ", "\n", __('quote')],
            ['list', __('List'), "\n- ", "\n", __('item')],
            ['list-ordered', __('Numbered list'), "\n1. ", "\n", __('item')],
            ['minus', __('Separator'), "\n\n---\n\n", '', ''],
        ],
        [
            ['link', __('Link').' (Ctrl+K)', '[', '](https://)', __('link text')],
            ['image', __('Image'), '![image](', ')', 'https://'],
            ['at', __('Mention a user'), '@', '', ''],
        ],
    ];
    // Original forum colors, used by [color=N]
    $colors = [1 => '#2f7fcc', 3 => '#6c77c1', 13 => '#98e2eb', 12 => '#00ffff', 7 => '#009d9d', 8 => '#2ecf73', 15 => '#a4cf9e', 4 => '#babd2f', 2 => '#cb546b', 14 => '#eb1d51', 10 => '#ed67ea', 9 => '#c53dff', 6 => '#606090', 5 => '#c2c2da'];
    $emojis = ['😀', '😂', '🤣', '😊', '😉', '😍', '😎', '🤔', '😮', '😢', '😭', '😡', '😴', '🤗', '🙄', '😇', '👍', '👎', '👏', '🙏', '💪', '🎉', '🔥', '⭐', '❤️', '💔', '✨', '💯', '🧀', '🐭', '🐱', '🏆', '🎮', '🗺️', '🛡️', '👑'];
    $previewing = $preview !== null;
@endphp
<div class="md-editor {{ $previewing ? 'is-previewing' : '' }}" x-data="{ length: 0, colors: false, emojis: false }">
    <div class="md-head">
        @if ($previewToggle)
            <div class="md-tabs" role="tablist">
                <button type="button" role="tab" class="{{ $previewing ? '' : 'active' }}" aria-selected="{{ $previewing ? 'false' : 'true' }}" @if ($previewing) wire:click="{{ $previewToggle }}" @endif><x-icon name="pencil" size="14" /> {{ __('Write') }}</button>
                <button type="button" role="tab" class="{{ $previewing ? 'active' : '' }}" aria-selected="{{ $previewing ? 'true' : 'false' }}" @unless ($previewing) wire:click="{{ $previewToggle }}" @endunless><x-icon name="eye" size="14" /> {{ __('Preview') }}</button>
            </div>
        @endif
        <div class="md-toolbar" data-toolbar="{{ $id }}" @if ($previewing) inert @endif>
            @foreach ($groups as $tools)
                <span class="md-group">
                    @foreach ($tools as [$icon, $title, $before, $after, $placeholder])
                        <button type="button" title="{{ $title }}" aria-label="{{ $title }}" data-md="{{ json_encode([$before, $after, $placeholder]) }}"><x-icon :name="$icon" /></button>
                    @endforeach
                </span>
            @endforeach
            <span class="md-group">
                <span class="md-pop" x-on:click.outside="colors = false">
                    <button type="button" title="{{ __('Text color') }}" aria-label="{{ __('Text color') }}" x-on:click="colors = ! colors; emojis = false" :class="colors && 'on'"><x-icon name="palette" /></button>
                    <span class="md-popover md-colors" x-show="colors" x-cloak x-on:click="colors = false">
                        @foreach ($colors as $code => $hex)
                            <button type="button" data-md="{{ json_encode(["[color={$code}]", '[/color]', __('text')]) }}" style="--swatch: {{ $hex }}" title="{{ $hex }}"></button>
                        @endforeach
                    </span>
                </span>
                <span class="md-pop" x-on:click.outside="emojis = false">
                    <button type="button" title="{{ __('Emojis') }}" aria-label="{{ __('Emojis') }}" x-on:click="emojis = ! emojis; colors = false" :class="emojis && 'on'"><x-icon name="smile" /></button>
                    <span class="md-popover md-emojis" x-show="emojis" x-cloak>
                        @foreach ($emojis as $emoji)
                            <button type="button" data-md="{{ json_encode([$emoji, '', '']) }}">{{ $emoji }}</button>
                        @endforeach
                    </span>
                </span>
            </span>
        </div>
    </div>

    <div class="md-body" @if ($previewing) hidden @endif>
        @if ($draft)
            {{-- Keep an unsent draft in this browser so a reload does not lose it --}}
            <textarea id="{{ $id }}" wire:model="{{ $model }}" maxlength="{{ $max }}" data-editor {{ $attributes }} x-data="draft(@js($draft))"
                x-init="$nextTick(() => $dispatch('md-length', $el.value.length))"
                x-on:input="$dispatch('md-length', $el.value.length)"
                x-on:input.debounce.500ms="save()" x-on:draft-sent.window="if ($event.detail.key === key) clear()"></textarea>
        @else
            <textarea id="{{ $id }}" wire:model="{{ $model }}" maxlength="{{ $max }}" data-editor {{ $attributes }}
                x-init="$nextTick(() => $dispatch('md-length', $el.value.length))"
                x-on:input="$dispatch('md-length', $el.value.length)"></textarea>
        @endif
    </div>
    @if ($previewing)
        <div class="md-preview post-body">{!! $preview ?: '<span class="muted">'.e(__('Nothing to preview.')).'</span>' !!}</div>
    @endif
    <div class="md-foot" x-on:md-length="length = $event.detail">
        <span>{{ __('Markdown supported') }} · <a href="{{ route('help') }}" target="_blank" rel="noopener"><x-icon name="help" size="12" /> {{ __('Formatting help') }}</a></span>
        <span class="md-count" :class="length > {{ (int) ($max * 0.9) }} && 'warn'"><span x-text="length"></span> / {{ number_format($max, 0, '', ' ') }}</span>
    </div>
</div>
@error($model)<div class="error">{{ $message }}</div>@enderror
