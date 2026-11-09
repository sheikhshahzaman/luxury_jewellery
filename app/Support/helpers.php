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

if (! function_exists('payment_methods_summary')) {
    /** "Bank transfer · JazzCash · Cash on delivery" — whatever checkout currently offers. */
    function payment_methods_summary(): string
    {
        static $summary = null;

        if ($summary === null) {
            $types = \App\Models\BankAccount::active()->distinct()->pluck('type')->all();
            $methods = collect(\App\Models\Order::PAYMENT_METHODS)
                ->filter(fn ($label, $method) => in_array($method, $types, true)
                    || ($method === 'cod' && \App\Services\CartService::codEnabled()))
                ->values();

            $summary = $methods->isEmpty() ? 'Bank transfer' : $methods->implode(' · ');
        }

        return $summary;
    }
}

if (! function_exists('metal_tone')) {
    /**
     * Map a metal name to a tone key used for swatches and the 3D viewer:
     * gold | white | rose | silver | platinum.
     */
    function metal_tone(string $name): string
    {
        $name = mb_strtolower($name);

        return match (true) {
            str_contains($name, 'rose') || str_contains($name, 'pink') => 'rose',
            str_contains($name, 'platinum') => 'platinum',
            str_contains($name, 'white') => 'white',
            str_contains($name, 'silver') || str_contains($name, 'steel') => 'silver',
            default => 'gold',
        };
    }
}

if (! function_exists('jewellery_model')) {
    /**
     * Which illustrative 3D model fits a category (ring | pendant | studs | bangle),
     * or null when there's no suitable one.
     */
    function jewellery_model(?\App\Models\Category $category): ?string
    {
        $key = mb_strtolower(($category?->slug ?? '').' '.($category?->name ?? ''));

        return match (true) {
            str_contains($key, 'ring') && ! str_contains($key, 'earring') => 'ring',
            str_contains($key, 'necklace') || str_contains($key, 'pendant') || str_contains($key, 'chain') => 'pendant',
            str_contains($key, 'earring') || str_contains($key, 'stud') => 'studs',
            str_contains($key, 'bracelet') || str_contains($key, 'bangle') || str_contains($key, 'cuff') => 'bangle',
            default => null,
        };
    }
}

if (! function_exists('asset_v')) {
    /**
     * asset() with a ?v=<file modified time> suffix, so browsers fetch the
     * new copy as soon as a CSS/JS file changes instead of a stale cache.
     */
    function asset_v(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}

if (! function_exists('vite_chunks')) {
    /**
     * Built file URLs for a Vite entry plus everything it statically imports,
     * used for <link rel="modulepreload|prefetch"> hints. Empty without a build
     * or while the Vite dev server runs.
     *
     * @return array<int, string>
     */
    function vite_chunks(string $entry): array
    {
        static $manifest = null;

        $path = public_path('build/manifest.json');
        if (! is_file($path) || is_file(public_path('hot'))) {
            return [];
        }
        $manifest ??= json_decode((string) file_get_contents($path), true) ?: [];

        $urls = [];
        $walk = function (string $key) use (&$walk, &$urls, $manifest) {
            $chunk = $manifest[$key] ?? null;
            if (! $chunk || isset($urls[$chunk['file']])) {
                return;
            }
            $urls[$chunk['file']] = asset('build/'.$chunk['file']);
            foreach ($chunk['imports'] ?? [] as $import) {
                $walk($import);
            }
        };
        $walk($entry);

        return array_values($urls);
    }
}

if (! function_exists('service_worker_url')) {
    /**
     * URL of the asset-caching service worker, versioned by the current build
     * so a new `npm run build` installs a fresh cache. Null when there's no build.
     */
    function service_worker_url(): ?string
    {
        $manifest = public_path('build/manifest.json');
        if (! is_file($manifest) || is_file(public_path('hot'))) {
            return null;
        }

        return asset('sw.js').'?build='.substr(md5_file($manifest), 0, 12);
    }
}

if (! function_exists('hero_frames')) {
    /**
     * The opening video's frame sets: public/media/hero/wide (landscape) and
     * public/media/hero/tall (portrait), each a run of 001.webp, 002.webp, …
     * The version is the newest frame's modified time, so re-exported frames
     * replace cached ones. Sets with no frames are left out.
     *
     * @return array<string, array{base: string, count: int, v: string, poster: string}>
     */
    function hero_frames(): array
    {
        $sets = [];
        foreach (['wide', 'tall'] as $name) {
            $files = glob(public_path("media/hero/{$name}/*.webp")) ?: [];
            if ($files === []) {
                continue;
            }
            $v = (string) max(array_map('filemtime', $files));
            $sets[$name] = [
                'base' => asset("media/hero/{$name}"),
                'count' => count($files),
                'v' => $v,
                'poster' => asset("media/hero/{$name}/001.webp").'?v='.$v,
            ];
        }

        return $sets;
    }
}
