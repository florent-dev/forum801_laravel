<x-layouts.app :title="$conversation->subject">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <livewire:conversation-view :conversation="$conversation" />
</x-layouts.app>
