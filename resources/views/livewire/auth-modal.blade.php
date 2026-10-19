<div x-data="{ tab: @entangle('tab') }" x-on:open-modal.window="if ($event.detail?.tab) tab = $event.detail.tab">
    <x-modal name="auth" :title="__('Welcome!')" width="400px">
        <div class="tabs modal-tabs">
            <a href="#" :class="tab === 'login' && 'active'" x-on:click.prevent="tab = 'login'">{{ __('Log in') }}</a>
            @if ($registrationOpen)<a href="#" :class="tab === 'register' && 'active'" x-on:click.prevent="tab = 'register'">{{ __('Create an account') }}</a>@endif
        </div>
        <form x-show="tab === 'login'" wire:submit="login">
            <div class="field"><label for="m-name">{{ __('Nickname') }}</label><input id="m-name" type="text" wire:model="name" autocomplete="username" autofocus>@error('name')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="m-password">{{ __('Password') }}</label><input id="m-password" type="password" wire:model="password" autocomplete="current-password">@error('password')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label><input type="checkbox" wire:model="remember"> {{ __('Remember me') }}</label></div>
            <div class="modal-actions">
                <button class="btn primary" type="submit" wire:loading.attr="disabled" wire:target="login">{{ __('Log in') }}</button>
            </div>
        </form>
        @if (! $registrationOpen)
            <div x-show="tab === 'register'" x-cloak class="empty">{{ __('Registration is currently closed.') }}</div>
        @else
        <form x-show="tab === 'register'" x-cloak wire:submit="register">
            <div class="field"><label for="m-rname">{{ __('Nickname') }}</label><input id="m-rname" type="text" wire:model="regName" maxlength="24" autocomplete="username">@error('regName')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="m-remail">{{ __('Email') }}</label><input id="m-remail" type="email" wire:model="regEmail" autocomplete="email">@error('regEmail')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="m-rpass">{{ __('Password') }}</label><input id="m-rpass" type="password" wire:model="regPassword" autocomplete="new-password">@error('regPassword')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="m-rpass2">{{ __('Confirm password') }}</label><input id="m-rpass2" type="password" wire:model="regPassword_confirmation" autocomplete="new-password"></div>
            @include('partials.captcha', ['model' => 'captcha', 'id' => 'm-captcha'])
            <div class="modal-actions">
                <button class="btn primary" type="submit" wire:loading.attr="disabled" wire:target="register">{{ __('Create my account') }}</button>
            </div>
        </form>
        @endif
    </x-modal>
</div>
