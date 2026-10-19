<?php

namespace App\Support;

use Illuminate\Http\Request;

class Community
{
    public static function all(): array
    {
        return config('forum.communities');
    }

    public static function exists(?string $code): bool
    {
        return $code !== null && array_key_exists($code, static::all());
    }

    // Community being browsed: chosen in this session, otherwise the one of the browser language
    public static function current(): string
    {
        $session = session('community');
        if (static::exists($session)) {
            return $session;
        }

        return static::fromRequest(request());
    }

    public static function set(string $code): void
    {
        // Never stored on the account: it only follows what the visitor browses
        session(['community' => $code]);
    }

    public static function fromRequest(Request $request): string
    {
        foreach ($request->getLanguages() as $lang) {
            $code = strtolower(substr($lang, 0, 2));
            // Brazilian Portuguese and Portuguese share one community
            $code = $code === 'pt' ? 'br' : $code;
            if (static::exists($code)) {
                return $code;
            }
        }

        return config('forum.default_community');
    }

    public static function locale(string $code): string
    {
        return static::all()[$code]['locale'] ?? config('app.fallback_locale');
    }

    public static function label(string $code): string
    {
        return static::all()[$code]['label'] ?? strtoupper($code);
    }

    public static function flagUrl(string $code): string
    {
        return asset('img/flags/'.(static::all()[$code]['flag'] ?? 'gb').'.png');
    }
}
