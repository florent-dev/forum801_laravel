<x-layouts.app :title="__('Admin').' · '.__('Sections')">
    <x-slot:left>@include('partials.admin-nav')</x-slot:left>
    <livewire:admin.sections />
</x-layouts.app>
