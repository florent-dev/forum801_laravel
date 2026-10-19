<x-layouts.app :drawer-open="true">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    {{-- The home page is the sections list; the main panel only invites to pick one --}}
    <div class="scroll home-empty">
        @guest
            <div class="form-box welcome">
                <h3>{{ __('Welcome to :name!', ['name' => config('app.name')]) }}</h3>
                @if (\App\Support\ForumSettings::registrationOpen())
                {{ __('Browse freely, or') }} <a href="{{ route('register') }}" x-data x-on:click.prevent="$dispatch('open-modal', { name: 'auth', tab: 'register' })">{{ __('create an account') }}</a> {{ __('to post, react, follow threads and chat with other users.') }}
                @else
                {{ __('Browse freely. Registration is currently closed.') }}
                @endif
            </div>
        @endguest
        <div class="empty">👈 {{ __('Pick a section on the left to start reading.') }}</div>
    </div>
</x-layouts.app>
