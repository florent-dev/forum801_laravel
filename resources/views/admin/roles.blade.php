<x-layouts.app :title="__('Admin').' · '.__('Roles')">
    <x-slot:left>@include('partials.admin-nav')</x-slot:left>
    <livewire:admin.roles />
</x-layouts.app>
