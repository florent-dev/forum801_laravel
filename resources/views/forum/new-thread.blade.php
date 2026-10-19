<x-layouts.app :title="__('New thread')">
    <x-slot:left><livewire:thread-list :section="$section" /></x-slot:left>
    <livewire:new-thread :section="$section" />
</x-layouts.app>
