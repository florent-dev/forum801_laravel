<x-layouts.app :title="$section->name" :drawer-open="true" bodyClass="no-main">
    {{-- The thread list on the left is the whole page; links to the feed and settings live in its toolbar --}}
    <x-slot:left><livewire:thread-list :section="$section" /></x-slot:left>
</x-layouts.app>
