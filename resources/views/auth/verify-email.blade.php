<x-layouts.app :title="__('Confirm your email')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <div class="scroll">
        <div class="form-box auth-box" style="text-align:center">
            <h3>✉️ {{ __('Confirm your email') }}</h3>
            <p>{{ __('We sent a confirmation link to :email. Click it to start creating threads, replying and sending messages.', ['email' => auth()->user()->email]) }}</p>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn primary" type="submit">{{ __('Send the link again') }}</button>
            </form>
            <p class="muted" style="font-size:11px">{{ __('Wrong address? Change it in your settings.') }} <a href="{{ route('settings') }}">{{ __('Settings') }}</a></p>
        </div>
    </div>
</x-layouts.app>
