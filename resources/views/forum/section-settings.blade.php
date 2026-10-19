<x-layouts.app :title="__(':section settings', ['section' => $section->name])">
    <x-slot:left><livewire:thread-list :section="$section" /></x-slot:left>
    <livewire:section-settings :section="$section" />
</x-layouts.app>
