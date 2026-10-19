{{-- Guild pages keep the guild forum in the left bar when the visitor can read it --}}
@if ($guild->section && auth()->user()?->can('view', $guild->section) || ($guild->section && $guild->forum_public))
    <livewire:thread-list :section="$guild->section" />
@else
    @include('partials.side-nav')
@endif
