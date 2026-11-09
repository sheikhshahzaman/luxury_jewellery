<?php

namespace App\Services;

use App\Models\MetalRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Everything the rates page, the header ticker and the JSON endpoint show,
 * built from two separate sources:
 *  - spot: international spot prices (SpotRates — fetched on a schedule or
 *    entered by hand in Admin), with per-gram / per-tola rupee conversions;
 *  - prices: the store's own buy/sell prices, set only in Admin (MetalRate).
 *
 * The numbers are the current database values; nothing is simulated.
 */
class LiveRates
{
    public const CACHE_KEY = 'live-rates.prices';

    public function __construct(protected SpotRates $spot)
    {
    }

    /** Admin prices as plain arrays, cached for a minute and cleared whenever the admin saves. */
    public function prices(): Collection
    {
        // Cached as a plain array: the cache store refuses to unserialize objects.
        return collect(Cache::remember(self::CACHE_KEY, 60, fn () => MetalRate::active()->ordered()->with('latestChange')->get()
            ->map(fn (MetalRate $r) => [
                'key' => $r->key,
                'label' => $r->displayLabel(),
                'size' => $r->label,
                'note' => $r->note,
                'metal' => $r->metal,
                'unit' => $r->unit,
                'quantity' => (float) $r->quantity,
                'buy' => (float) $r->buy,
                'sell' => (float) $r->sell,
                'currency' => 'PKR',
                // Direction of the admin's most recent change to the buy price.
                'change' => $r->latestChange && $r->latestChange->buy_before !== null
                    ? (float) $r->latestChange->buy - (float) $r->latestChange->buy_before : 0.0,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ])->all()));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** The 1-tola price of a metal (else its first price), for the ticker and home page. */
    public function headline(string $metal): ?array
    {
        $rows = $this->prices()->where('metal', $metal);

        return $rows->first(fn ($r) => $r['unit'] === 'tola' && $r['quantity'] == 1) ?? $rows->first();
    }

    /** The JSON endpoint's payload; also rendered server-side by the rates page. */
    public function snapshot(): array
    {
        $current = $this->spot->current();
        $fx = $current['USDPKR'] ?? null;

        $metal = function (?array $s) use ($fx) {
            if (! $s) {
                return null;
            }
            $perGram = $s['mid'] / config('rates.grams_per_troy_ounce');

            return [
                'bid_usd_oz' => $s['bid'],
                'ask_usd_oz' => $s['ask'],
                'price' => round($s['mid'], 2),
                'change' => round($s['change'], 2),
                'change_pct' => $s['change_pct'],
                'history' => $s['history'],
                'usd_per_gram' => round($perGram, 4),
                'pkr_per_gram' => $fx ? round($perGram * $fx['mid']) : null,
                'pkr_per_tola' => $fx ? round($perGram * config('rates.grams_per_tola') * $fx['mid']) : null,
                'source' => $s['source'],
                'updated_at' => $s['updated_at'],
            ];
        };

        $gold = $metal($current['XAU'] ?? null);
        $silver = $metal($current['XAG'] ?? null);
        // The overall time is the older of the two, so it never overstates freshness.
        $times = array_filter([$gold['updated_at'] ?? null, $silver['updated_at'] ?? null]);

        return [
            'spot' => [
                'gold' => $gold,
                'silver' => $silver,
                'usd_pkr' => $fx ? ['buy' => $fx['bid'], 'sell' => $fx['ask'], 'source' => $fx['source'], 'updated_at' => $fx['updated_at']] : null,
                'mode' => $this->spot->mode(),
                'updated_at' => $times ? min($times) : null,
            ],
            'prices' => $this->prices()->values()->all(),
            'poll_seconds' => (int) config('rates.poll_seconds', 30),
        ];
    }
}
