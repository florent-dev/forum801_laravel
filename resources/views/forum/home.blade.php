<x-layouts.app :drawer-open="true" bodyClass="no-main">
    {{-- The home page is the sections list on the left, without a main frame --}}
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
</x-layouts.app>
