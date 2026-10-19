@props(['name', 'size' => 20])
{{-- Illustrated icon in the style of the section icons (public/img/icons, see resources/icons/generate.py) --}}
<img src="{{ asset('img/icons/'.$name.'.svg') }}" width="{{ $size }}" height="{{ $size }}" alt="" aria-hidden="true" {{ $attributes->merge(['class' => 'art-icon']) }}>
