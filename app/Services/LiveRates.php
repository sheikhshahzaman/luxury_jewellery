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
 *  - prices: the store's own buy/sell prices (MetalRate). Manual items are
 *    exactly what the admin typed; auto items are spot × weight × dollar rate
 *    plus the item's margin, so they move with every spot reading.
 *
 * Browsers may shuffle the numbers slightly on screen (see display()); the
 * values served here are always the real ones.
 */
class LiveRates
{
    public const CACHE_KEY = 'live-rates.prices';

    public function __construct(protected SpotRates $spot)
    {
    }

    /**
     * The price board as plain arrays. The admin's rows are cached for a minute
     * (cleared whenever the admin saves); auto items are then priced from the
     * current spot values, so they change as soon as a new reading is saved.
     */
    public function prices(): Collection
    {
        // Cached as a plain array: the cache store refuses to unserialize objects.
        $rows = Cache::remember(self::CACHE_KEY, 60, fn () => MetalRate::active()->ordered()->with('latestChange')->get()
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
                // Used below for auto items, never sent to the browser.
                '_auto' => $r->isAuto(),
                '_grams' => $r->grams(),
                '_margins' => [(float) $r->buy_margin, (float) $r->sell_margin],
            ])->all());

        $spot = $this->spot->current();

        return collect($rows)->map(function (array $row) use ($spot) {
            $auto = $row['_auto'] ? $this->autoPrice($row['metal'], $row['_grams'], $row['_margins'][0], $row['_margins'][1], $spot) : null;
            if ($auto) {
                $row = array_merge($row, $auto);
            }
            $row['auto'] = (bool) $auto;
            unset($row['_auto'], $row['_grams'], $row['_margins']);

            return $row;
        });
    }

    /**
     * Buy and sell in rupees for an item priced from spot, or null when the
     * spot price or the dollar rate is missing (the saved price is shown then).
     *
     *   price = spot USD/oz ÷ 31.1035 × grams × rupees per dollar × (1 + margin%)
     *
     * Spot is the quoted price shown on the page (the bid), for buy and sell
     * alike; the difference between the buy and sell margins is the spread.
     *
     * @param  array<string, array|null>|null  $spot  SpotRates::current(), fetched if not given
     * @return array{buy: float, sell: float, change: float, updated_at: string}|null
     */
    public function autoPrice(string $metal, float $grams, float $buyMargin, float $sellMargin, ?array $spot = null): ?array
    {
        $spot ??= $this->spot->current();
        $s = $spot[MetalRate::INSTRUMENT[$metal] ?? ''] ?? null;
        $fx = $spot['USDPKR'] ?? null;
        if (! $s || ! $fx || $grams <= 0) {
            return null;
        }

        $ounces = $grams / config('rates.grams_per_troy_ounce');
        $atSpot = $s['bid'] * $ounces * $fx['mid'];
        $buy = round($atSpot * (1 + $buyMargin / 100));
        $sell = round($atSpot * (1 + $sellMargin / 100));
        // The same move, in rupees, that spot made over the last day.
        $before = $s['mid'] - $s['change'];

        return [
            'buy' => $buy,
            'sell' => $sell,
            'change' => $before > 0 ? round($buy - $buy * $before / $s['mid']) : 0.0,
            'updated_at' => $s['updated_at'],
        ];
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
            // The quoted spot price is the bid, as on Kitco and TradingView;
            // the rupee figures beside it use the same number.
            $perGram = $s['bid'] / config('rates.grams_per_troy_ounce');

            return [
                'bid_usd_oz' => $s['bid'],
                'ask_usd_oz' => $s['ask'],
                'price' => round($s['bid'], 2),
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
            'display' => $this->display(),
        ];
    }

    /**
     * How browsers animate the numbers between real updates. The values above
     * are always the exact stored ones; the shuffle is applied on screen only,
     * as a small offset from them (never cumulative), so it can't drift.
     */
    public function display(): array
    {
        $min = max(0, (int) setting('rates_shuffle_pkr_min', 10));

        return [
            'shuffle' => setting('rates_shuffle', '1') === '1',
            'seconds' => max(2, (int) setting('rates_shuffle_seconds', 3)),
            'pkr' => [$min, max($min, (int) setting('rates_shuffle_pkr_max', 20))],
            'cents' => config('rates.shuffle_cents'),
        ];
    }
}
