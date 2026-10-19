<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Maintenance') }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/sections/Discussions.png') }}">
    <style>
        * { box-sizing: border-box; font-family: Verdana, Geneva, sans-serif; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; background: #0e242d; color: #c9c9c9; }
        .card { max-width: 520px; width: 100%; text-align: center; background: #1c3c41; border-radius: 12px; padding: 32px 28px; box-shadow: inset 0 1px #315b66, 0 20px 60px rgba(0,0,0,.5); }
        .logo { height: 56px; margin-bottom: 12px; }
        .cheese { font-size: 48px; display: inline-block; animation: wobble 2.4s ease-in-out infinite; }
        @keyframes wobble { 0%, 100% { transform: rotate(-8deg); } 50% { transform: rotate(8deg); } }
        h1 { color: #009d9d; font-weight: normal; font-size: 22px; margin: 12px 0 8px; }
        p { line-height: 1.6; font-size: 14px; margin: 8px 0; }
        .message { background: #0e242d; border-radius: 8px; padding: 12px 14px; margin: 16px 0; white-space: pre-line; }
        .muted { color: #7d9399; font-size: 13px; }
        a { color: #009d9d; }
        @media (prefers-reduced-motion: reduce) { .cheese { animation: none; } }
    </style>
</head>
<body>
    <main class="card">
        <img class="logo" src="{{ asset('img/logo.png') }}" alt="{{ config('app.name') }}">
        <div class="cheese" aria-hidden="true">🧀</div>
        <h1>{{ __('The forum is under maintenance') }}</h1>
        <p>{{ __('The mice are busy fixing a few things. We will be back very soon!') }}</p>
        @if (! empty($state['message']))
            <div class="message">{{ $state['message'] }}</div>
        @endif
        @if (! empty($state['until']))
            <p class="muted">{{ __('Expected back :when.', ['when' => \Illuminate\Support\Carbon::parse($state['until'])->diffForHumans()]) }}</p>
        @endif
    </main>
</body>
</html>
