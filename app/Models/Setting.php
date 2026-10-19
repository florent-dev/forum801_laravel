<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    // Settings are read on every request, so they are cached until changed
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever('setting:'.$key, fn () => static::find($key)?->value);

        return $value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('setting:'.$key);
    }
}
