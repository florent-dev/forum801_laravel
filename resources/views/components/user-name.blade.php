@props(['user'])
@if ($user)
<a href="{{ route('profile', $user) }}" wire:navigate style="color: {{ $user->displayColor() }}" {{ $attributes }}>{{ $user->name }}</a>
@else
<span class="muted">{{ __('deleted') }}</span>
@endif
