<?php

/*
| Spot-rate fetching (app/Services/SpotRates.php, run by `php artisan rates:fetch`
| on the scheduler). Whether the site uses fetched or hand-entered spot values
| is chosen in Admin → Live rates; this file only configures the providers.
*/
return [

    // Gold & silver spot, USD per troy ounce:
    //   gold_api    api.gold-api.com — free, no key, one price (bid = ask)
    //   goldapi_io  www.goldapi.io   — needs GOLDAPI_IO_KEY, real bid/ask
    'provider' => env('RATES_PROVIDER', 'gold_api'),

    'goldapi_io_key' => env('GOLDAPI_IO_KEY'),

    // USD → PKR for rupee conversions: open_er_api (free, no key, updates daily) or "none".
    'fx_provider' => env('RATES_FX_PROVIDER', 'open_er_api'),

    // How often the scheduler fetches, in minutes (1–60).
    'fetch_every_minutes' => (int) env('RATES_FETCH_EVERY', 5),

    // Per request: seconds before giving up, and waits (ms) between retries.
    'timeout' => 8,
    'retry_backoff_ms' => [500, 1500, 4000],

    // Spot history older than this is deleted daily (0 keeps everything).
    'keep_history_days' => (int) env('RATES_KEEP_DAYS', 180),

    // How often open pages ask the server for fresh numbers, in seconds.
    'poll_seconds' => 30,

    // Readings outside these ranges are treated as bad data and not saved.
    'sane' => [
        'XAU' => [300, 50000],
        'XAG' => [3, 2000],
        'USDPKR' => [50, 2000],
    ],

    // Display shuffle (on/off, rupee range and speed are set in Admin → Live rates):
    // spot prices move only after the decimal point, by this many cents.
    'shuffle_cents' => [
        'gold' => [5, 45],
        'silver' => [1, 5],
    ],

    'grams_per_troy_ounce' => 31.1035,
    'grams_per_tola' => 11.6638,
];
