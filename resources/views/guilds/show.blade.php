<x-layouts.app :title="$guild->name">
    <x-slot:left>@include('partials.guild-left')</x-slot:left>
    <livewire:guild-page :guild="$guild" />
</x-layouts.app>
