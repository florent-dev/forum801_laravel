<x-layouts.app :title="__('System health')">
    <x-slot:left>@include('partials.admin-nav')</x-slot:left>
    <livewire:admin.health />
</x-layouts.app>
