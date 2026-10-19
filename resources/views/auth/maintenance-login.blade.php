<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Staff login') }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/sections/Discussions.png') }}">
    <style>
        * { box-sizing: border-box; font-family: Verdana, Geneva, sans-serif; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; background: #0e242d; color: #c9c9c9; }
        .card { max-width: 380px; width: 100%; background: #1c3c41; border-radius: 12px; padding: 28px 26px; box-shadow: inset 0 1px #315b66, 0 20px 60px rgba(0,0,0,.5); }
        .logo { display: block; height: 48px; margin: 0 auto 10px; }
        h1 { color: #009d9d; font-weight: normal; font-size: 18px; text-align: center; margin: 4px 0 6px; }
        .muted { color: #7d9399; font-size: 12.5px; text-align: center; margin: 0 0 18px; line-height: 1.5; }
        label { display: block; font-size: 12px; margin: 12px 0 5px; }
        input[type=text], input[type=password] { width: 100%; background: #0e242d; color: #009d9d; border: 1px solid #315b66; border-radius: 4px; padding: 8px; font-size: 14px; }
        input:focus { outline: 2px solid #009d9d; outline-offset: 1px; }
        .remember { display: flex; align-items: center; gap: 6px; font-size: 12px; margin-top: 12px; }
        button { width: 100%; margin-top: 18px; padding: 9px; font-size: 14px; color: #fff; background: #009d9d; border: 1px solid #00b8b8; border-radius: 5px; cursor: pointer; }
        button:hover { background: #00b3b3; }
        .error { color: #ff6b85; font-size: 12px; margin-top: 6px; }
        .back { display: block; text-align: center; margin-top: 16px; font-size: 12px; color: #009d9d; }
    </style>
</head>
<body>
    <main class="card">
        <img class="logo" src="{{ asset('img/logo.png') }}" alt="{{ config('app.name') }}">
        <h1>🛠️ {{ __('Staff login') }}</h1>
        <p class="muted">{{ __('The forum is under maintenance') }}. {{ __('Only the forum team can log in until it reopens.') }}</p>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="nicknameinput">{{ __('Nickname') }}</label>
            <input id="nicknameinput" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="username">
            @error('name')<div class="error">{{ $message }}</div>@enderror
            <label for="passwordinput">{{ __('Password') }}</label>
            <input id="passwordinput" type="password" name="password" required autocomplete="current-password">
            <label class="remember"><input type="checkbox" name="remember" value="1"> {{ __('Remember me') }}</label>
            <button type="submit">{{ __('Log in') }}</button>
        </form>
    </main>
</body>
</html>
