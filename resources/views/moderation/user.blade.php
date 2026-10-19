<x-layouts.app :title="__('User file').' · '.$user->name">
    <x-slot:left><livewire:report-list /></x-slot:left>
    <livewire:user-file :user="$user" />
</x-layouts.app>
