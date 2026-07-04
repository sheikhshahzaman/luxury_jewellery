<?php

use App\Models\Setting;
use Illuminate\Support\Str;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('setting_image')) {
    /**
     * Resolve an image setting (stored as a full URL or a public-disk path)
     * to a usable URL. Returns $default when the setting is empty.
     */
    function setting_image(string $key, ?string $default = null): ?string
    {
        $value = setting($key);
        if (! $value) {
            return $default;
        }

        return Str::startsWith($value, ['http://', 'https://'])
            ? $value
            : asset('storage/'.$value);
    }
}

if (! function_exists('price_format')) {
    function price_format(mixed $amount): string
    {
        $amount = (float) $amount;
        $symbol = setting('currency_symbol', '$');
        $decimals = fmod($amount, 1) == 0.0 ? 0 : 2;

        return $symbol.number_format($amount, $decimals);
    }
}

if (! function_exists('cart_count')) {
    function cart_count(): int
    {
        return app(\App\Services\CartService::class)->count();
    }
}
