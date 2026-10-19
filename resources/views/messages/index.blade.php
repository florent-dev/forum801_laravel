<x-layouts.app :title="__('Messages')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <livewire:inbox />
</x-layouts.app>
