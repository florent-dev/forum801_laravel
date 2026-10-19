<x-layouts.app :title="__('Log in')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <div class="scroll">
        <form class="form-box auth-box" method="POST" action="{{ route('login') }}">
            @csrf
            <h3>{{ __('Log in') }}</h3>
            <div class="field"><label for="nicknameinput">{{ __('Nickname') }}</label><input id="nicknameinput" type="text" name="name" value="{{ old('name') }}" required autofocus>@error('name')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="passwordinput">{{ __('Password') }}</label><input id="passwordinput" type="password" name="password" required></div>
            <div class="field"><label><input type="checkbox" name="remember" value="1"> {{ __('Remember me') }}</label></div>
            <button class="btn primary" type="submit">{{ __('Log in') }}</button>
            @if (\App\Support\ForumSettings::registrationOpen())<a href="{{ route('register') }}" style="margin-left:8px;font-size:11px">{{ __('Create an account') }}</a>@endif
        </form>
    </div>
</x-layouts.app>
