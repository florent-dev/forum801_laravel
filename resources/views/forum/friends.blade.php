<x-layouts.app :title="__('Friends')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <livewire:friends-page />
</x-layouts.app>
