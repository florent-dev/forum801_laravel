<x-layouts.app :title="__('Admin').' · '.__('Users')">
    <x-slot:left>@include('partials.admin-nav')</x-slot:left>
    <livewire:admin.users />
</x-layouts.app>
