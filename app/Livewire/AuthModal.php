<?php

namespace App\Livewire;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AuthModal extends Component
{
    public string $tab = 'login';

    public string $name = '';

    public string $password = '';

    public bool $remember = false;

    public string $regName = '';

    public string $regEmail = '';

    public string $regPassword = '';

    public string $regPassword_confirmation = '';

    public string $captcha = '';

    // Honeypot: hidden from people, filled by bots
    public string $website = '';

    public function login()
    {
        $this->validate(['name' => ['required', 'string'], 'password' => ['required', 'string']]);
        $key = 'login:'.Str::lower($this->name).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            app(\App\Services\SecurityService::class)->record('lockout', null, ['name' => $this->name]);
            throw ValidationException::withMessages(['name' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        if (! Auth::attempt(['name' => $this->name, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            $this->reset('password');
            throw ValidationException::withMessages(['name' => __('These credentials do not match our records.')]);
        }
        RateLimiter::clear($key);
        session()->regenerate();
        session()->flash('toast', __('Welcome back, :name!', ['name' => Auth::user()->name]));

        return $this->redirect($this->backUrl());
    }

    public function register()
    {
        if (! \App\Support\ForumSettings::registrationOpen()) {
            throw ValidationException::withMessages(['regName' => __('Registration is currently closed.')]);
        }
        $key = 'register:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['regName' => __('Too many accounts created. Try again later.')]);
        }
        // Bots fill the hidden field: refuse without telling why
        if ($this->website !== '') {
            throw ValidationException::withMessages(['regName' => __('Registration failed. Please try again.')]);
        }
        $captchaOk = app(\App\Services\CaptchaService::class)->check($this->captcha);
        $this->reset('captcha');
        $this->dispatch('captcha-refresh');
        if (! $captchaOk) {
            throw ValidationException::withMessages(['captcha' => __('The characters do not match. Try the new image.')]);
        }
        $data = $this->validate([
            'regName' => ['required', 'string', 'min:3', 'max:24', 'alpha_dash', 'unique:users,name'],
            'regEmail' => ['required', 'email:rfc,filter', 'not_regex:/[\r\n]/', 'max:255', 'unique:users,email'],
            'regPassword' => ['required', 'confirmed', Password::defaults()],
        ], [], ['regName' => __('nickname'), 'regEmail' => __('email'), 'regPassword' => __('password')]);
        RateLimiter::hit($key, 3600);

        $user = User::create(['name' => $data['regName'], 'email' => $data['regEmail'], 'password' => $data['regPassword'], 'locale' => \App\Support\Locale::current()]);
        $user->assignRole(Role::MEMBER);
        event(new Registered($user));
        Auth::login($user);
        session()->regenerate();
        session()->flash('toast', __('Welcome to the forum, :name!', ['name' => $user->name]));

        return $this->redirect($this->backUrl());
    }

    // Return to the page the visitor came from, only on this site
    protected function backUrl(): string
    {
        $previous = url()->previous();

        return $previous && parse_url($previous, PHP_URL_HOST) === request()->getHost() ? $previous : route('home');
    }

    public function render()
    {
        view()->share('registrationOpen', \App\Support\ForumSettings::registrationOpen());
        return view('livewire.auth-modal');
    }
}
