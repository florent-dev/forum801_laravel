<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin(\App\Services\MaintenanceService $maintenance)
    {
        // During maintenance the regular page would expose the sections list
        if ($maintenance->isEnabled()) {
            return view('auth.maintenance-login', ['state' => $maintenance->state()]);
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'name' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['name' => __('These credentials do not match our records.')]);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('toast', __('Welcome back, :name!', ['name' => Auth::user()->name]));
    }

    public function showRegister()
    {
        abort_unless(\App\Support\ForumSettings::registrationOpen(), 403, __('Registration is currently closed.'));

        return view('auth.register');
    }

    public function register(Request $request)
    {
        abort_unless(\App\Support\ForumSettings::registrationOpen(), 403, __('Registration is currently closed.'));
        if ($request->filled('website')) {
            return back()->withErrors(['name' => __('Registration failed. Please try again.')])->withInput($request->except('password', 'password_confirmation', 'captcha'));
        }
        if (! app(\App\Services\CaptchaService::class)->check($request->input('captcha'))) {
            return back()->withErrors(['captcha' => __('The characters do not match. Try the new image.')])->withInput($request->except('password', 'password_confirmation', 'captcha'));
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:24', 'alpha_dash', 'unique:users,name'],
            'email' => ['required', 'email:rfc,filter', 'not_regex:/[\r\n]/', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($data + ['locale' => \App\Support\Locale::current()]);
        $user->assignRole(Role::MEMBER);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('toast', __('Welcome to the forum, :name!', ['name' => $user->name]));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('toast', __('See you soon!'));
    }
}
