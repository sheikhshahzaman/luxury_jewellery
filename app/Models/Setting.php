<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** In-request cache of all settings. */
    protected static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (static::$cache === null) {
            try {
                static::$cache = static::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                // Table may not exist yet (e.g. during migrations).
                return $default;
            }
        }

        $value = static::$cache[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::$cache = null;
    }

    public static function flushCache(): void
    {
        static::$cache = null;
    }
}
