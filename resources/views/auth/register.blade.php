<x-layouts.app :title="__('Register')">
    <x-slot:left>@include('partials.side-nav')</x-slot:left>
    <div class="scroll">
        <form class="form-box auth-box" method="POST" action="{{ route('register') }}">
            @csrf
            <h3>{{ __('Create an account') }}</h3>
            <div class="field"><label for="name">{{ __('Nickname') }}</label><input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="24">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="email">{{ __('Email') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required>@error('email')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="password">{{ __('Password') }}</label><input id="password" type="password" name="password" required>@error('password')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="password_confirmation">{{ __('Confirm password') }}</label><input id="password_confirmation" type="password" name="password_confirmation" required></div>
            @include('partials.captcha', ['model' => null])
            <button class="btn primary" type="submit">{{ __('Register') }}</button>
        </form>
    </div>
</x-layouts.app>
