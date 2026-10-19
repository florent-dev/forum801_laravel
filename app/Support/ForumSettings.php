<?php

namespace App\Support;

use App\Models\Setting;

// Forum options changed from Admin › Settings; config/forum.php gives the defaults
class ForumSettings
{
    public static function defaults(): array
    {
        return [
            'registration_open' => true,
            'captcha' => (bool) config('forum.captcha'),
            'posts_per_minute' => 5,
            'messages_per_minute' => 10,
            'max_post_length' => 20000,
            'max_guilds_per_user' => (int) config('forum.max_guilds_per_user'),
            // Minutes during which authors may edit their posts; null means no limit
            'edit_window_minutes' => null,
        ];
    }

    public static function all(): array
    {
        return array_merge(static::defaults(), (array) Setting::get('forum_settings', []));
    }

    public static function get(string $key): mixed
    {
        return static::all()[$key] ?? null;
    }

    public static function registrationOpen(): bool
    {
        return (bool) static::get('registration_open');
    }

    public static function maxPostLength(): int
    {
        return (int) static::get('max_post_length');
    }

    public static function save(array $values): void
    {
        Setting::put('forum_settings', array_merge((array) Setting::get('forum_settings', []), array_intersect_key($values, static::defaults())));
    }
}
