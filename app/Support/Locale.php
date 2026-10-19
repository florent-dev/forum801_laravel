<?php

namespace App\Support;

use Illuminate\Http\Request;

// Interface language of the visitor, separate from the community being browsed
class Locale
{
    public static function all(): array
    {
        return config('forum.locales');
    }

    public static function exists(?string $code): bool
    {
        return $code !== null && array_key_exists($code, static::all());
    }

    // Account setting first, then the session, then the browser
    public static function current(): string
    {
        $user = auth()->user();
        if ($user && static::exists($user->locale)) {
            return $user->locale;
        }
        $session = session('locale');

        return static::exists($session) ? $session : static::fromRequest(request());
    }

    public static function set(string $code): void
    {
        session(['locale' => $code]);
        auth()->user()?->forceFill(['locale' => $code])->saveQuietly();
    }

    public static function fromRequest(?Request $request): string
    {
        foreach ($request?->getLanguages() ?? [] as $lang) {
            $lang = str_replace('-', '_', $lang);
            // Exact match first (pt_BR), then the bare language (pt → pt_BR)
            if (static::exists($lang)) {
                return $lang;
            }
            $short = strtolower(substr($lang, 0, 2));
            foreach (array_keys(static::all()) as $code) {
                if (strtolower(substr($code, 0, 2)) === $short) {
                    return $code;
                }
            }
        }

        return config('forum.default_locale');
    }

    public static function label(string $code): string
    {
        return static::all()[$code]['label'] ?? $code;
    }

    public static function flagUrl(string $code): string
    {
        return asset('img/flags/'.(static::all()[$code]['flag'] ?? 'gb').'.png');
    }
}
