<x-layouts.app :title="__('Manage :name', ['name' => $guild->name])">
    <x-slot:left>@include('partials.guild-left')</x-slot:left>
    <livewire:guild-manage :guild="$guild" />
</x-layouts.app>
