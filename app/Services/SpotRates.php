<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SpotRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * International spot rates: XAU and XAG in USD per troy ounce, and USD→PKR.
 *
 * In "auto" mode the scheduler calls fetch(), which asks the configured
 * provider and saves each reading that passes validation. In "manual" mode
 * the admin types the values in instead (saveManual). Either way a reading is
 * only ever added, never edited, and a failed or implausible reading is
 * simply not saved, so the last good value and its timestamp stay current.
 *
 * Never touches the store's own buy/sell prices (MetalRate).
 */
class SpotRates
{
    public const CACHE_KEY = 'spot-rates.current';

    public const HISTORY_POINTS = 36;

    public function mode(): string
    {
        return setting('spot_mode', 'auto') === 'manual' ? 'manual' : 'auto';
    }

    /**
     * Fetch from the providers and save what's valid.
     *
     * @return array{saved: array<string, float>, failed: array<string, string>}
     */
    public function fetch(): array
    {
        $saved = [];
        $failed = [];
        $now = now();

        $readings = [];
        try {
            $readings += $this->fetchMetals();
        } catch (Throwable $e) {
            $failed['XAU'] = $failed['XAG'] = $e->getMessage();
        }
        if (config('rates.fx_provider') !== 'none') {
            try {
                $readings += $this->fetchUsdPkr();
            } catch (Throwable $e) {
                $failed['USDPKR'] = $e->getMessage();
            }
        }

        foreach ($readings as $instrument => [$bid, $ask, $source]) {
            $problem = $this->invalid($instrument, $bid, $ask);
            if ($problem) {
                $failed[$instrument] = $problem;

                continue;
            }
            SpotRate::create(['instrument' => $instrument, 'bid' => $bid, 'ask' => $ask, 'source' => $source, 'fetched_at' => $now]);
            $saved[$instrument] = ($bid + $ask) / 2;
        }

        if ($failed) {
            Log::warning('Spot rate fetch: some readings were not saved; keeping the last good values.', $failed);
        }
        Setting::set('spot_last_attempt_at', $now->toIso8601String());
        Setting::set('spot_last_error', $failed ? collect($failed)->map(fn ($m, $i) => "$i: $m")->implode('; ') : '');
        self::forget();

        return compact('saved', 'failed');
    }

    /**
     * Save hand-entered spot values (manual mode).
     *
     * @param  array<string, array{bid: float|string, ask: float|string}>  $values  instrument => bid/ask
     */
    public function saveManual(array $values): void
    {
        $now = now();
        foreach ($values as $instrument => $v) {
            $bid = (float) $v['bid'];
            $ask = (float) $v['ask'];
            if ($problem = $this->invalid($instrument, $bid, $ask)) {
                throw new RuntimeException("$instrument: $problem");
            }
            SpotRate::create(['instrument' => $instrument, 'bid' => $bid, 'ask' => $ask, 'source' => 'manual', 'fetched_at' => $now]);
        }
        self::forget();
    }

    /**
     * Newest reading per instrument, with its change over ~24 hours and a
     * short history for sparklines. Cached briefly; cleared on every save.
     *
     * @return array<string, array{bid: float, ask: float, mid: float, change: float, change_pct: float, history: array<int, float>, source: string, updated_at: string}|null>
     */
    public function current(): array
    {
        return Cache::remember(self::CACHE_KEY, 30, function () {
            $out = [];
            foreach (SpotRate::INSTRUMENTS as $instrument) {
                $latest = SpotRate::for($instrument)->newest()->first();
                if (! $latest) {
                    $out[$instrument] = null;

                    continue;
                }

                // History and change only compare readings from the same source, so a
                // hand-typed or placeholder value never shows up as a market move.
                $same = fn () => SpotRate::for($instrument)->where('source', $latest->source);
                $recent = $same()->newest()->limit(self::HISTORY_POINTS)->get();

                // Compare with the reading from about a day earlier (or the oldest we have).
                $before = $same()->where('fetched_at', '<=', $latest->fetched_at->copy()->subDay())->newest()->first()
                    ?? $same()->orderBy('fetched_at')->orderBy('id')->first();
                $change = $before && $before->id !== $latest->id ? $latest->mid() - $before->mid() : 0.0;

                $out[$instrument] = [
                    'bid' => round($latest->bid, 4),
                    'ask' => round($latest->ask, 4),
                    'mid' => round($latest->mid(), 4),
                    'change' => round($change, 4),
                    'change_pct' => $before && $before->mid() > 0 ? round($change / $before->mid() * 100, 3) : 0.0,
                    'history' => $recent->reverse()->map(fn (SpotRate $r) => round($r->mid(), 4))->values()->all(),
                    'source' => $latest->source,
                    'updated_at' => $latest->fetched_at->toIso8601String(),
                ];
            }

            return $out;
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Delete history older than the configured number of days, keeping the newest reading of each instrument. */
    public function prune(): int
    {
        $days = (int) config('rates.keep_history_days');
        if ($days <= 0) {
            return 0;
        }

        $deleted = 0;
        foreach (SpotRate::INSTRUMENTS as $instrument) {
            $newest = SpotRate::for($instrument)->newest()->value('id');
            $deleted += SpotRate::for($instrument)
                ->where('fetched_at', '<', now()->subDays($days))
                ->when($newest, fn ($q) => $q->where('id', '!=', $newest))
                ->delete();
        }

        return $deleted;
    }

    // ---------------------------------------------------------------------
    // Providers
    // ---------------------------------------------------------------------

    /** @return array<string, array{0: float, 1: float, 2: string}> */
    protected function fetchMetals(): array
    {
        return match ($provider = config('rates.provider')) {
            'gold_api' => [
                'XAU' => $this->goldApi('XAU'),
                'XAG' => $this->goldApi('XAG'),
            ],
            'goldapi_io' => [
                'XAU' => $this->goldApiIo('XAU'),
                'XAG' => $this->goldApiIo('XAG'),
            ],
            default => throw new RuntimeException("Unknown spot provider \"$provider\" (config/rates.php)."),
        };
    }

    /** api.gold-api.com: a single price, used as both bid and ask. */
    protected function goldApi(string $symbol): array
    {
        $price = $this->get("https://api.gold-api.com/price/{$symbol}")['price'] ?? null;
        if (! is_numeric($price)) {
            throw new RuntimeException('gold-api.com returned no price');
        }

        return [(float) $price, (float) $price, 'gold-api.com'];
    }

    /** goldapi.io: real bid/ask; needs an API key. */
    protected function goldApiIo(string $symbol): array
    {
        $key = config('rates.goldapi_io_key');
        if (! $key) {
            throw new RuntimeException('GOLDAPI_IO_KEY is not set');
        }
        $data = $this->get("https://www.goldapi.io/api/{$symbol}/USD", ['x-access-token' => $key]);
        $bid = $data['bid'] ?? $data['price'] ?? null;
        $ask = $data['ask'] ?? $data['price'] ?? null;
        if (! is_numeric($bid) || ! is_numeric($ask)) {
            throw new RuntimeException('goldapi.io returned no bid/ask');
        }

        return [(float) $bid, (float) $ask, 'goldapi.io'];
    }

    /** @return array<string, array{0: float, 1: float, 2: string}> */
    protected function fetchUsdPkr(): array
    {
        if (($provider = config('rates.fx_provider')) !== 'open_er_api') {
            throw new RuntimeException("Unknown FX provider \"$provider\" (config/rates.php).");
        }
        $data = $this->get('https://open.er-api.com/v6/latest/USD');
        $rate = $data['rates']['PKR'] ?? null;
        if (($data['result'] ?? null) !== 'success' || ! is_numeric($rate)) {
            throw new RuntimeException('open.er-api.com returned no PKR rate');
        }

        return ['USDPKR' => [(float) $rate, (float) $rate, 'open.er-api.com']];
    }

    /** GET JSON with a timeout and retries with backoff. */
    protected function get(string $url, array $headers = []): array
    {
        $response = Http::acceptJson()
            ->withUserAgent('LaxJewellers-rates/1.0')
            ->withHeaders($headers)
            ->timeout((int) config('rates.timeout', 8))
            ->retry(config('rates.retry_backoff_ms', [500, 1500, 4000]), 0, null, false)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(parse_url($url, PHP_URL_HOST)." answered HTTP {$response->status()}");
        }

        return (array) $response->json();
    }

    /** Why a reading can't be saved, or null if it's fine. */
    protected function invalid(string $instrument, float $bid, float $ask): ?string
    {
        if (! in_array($instrument, SpotRate::INSTRUMENTS, true)) {
            return 'unknown instrument';
        }
        if (! is_finite($bid) || ! is_finite($ask) || $bid <= 0 || $ask <= 0) {
            return 'not a positive number';
        }
        if ($ask < $bid) {
            return 'ask is below bid';
        }
        [$min, $max] = config("rates.sane.$instrument");
        if ($bid < $min || $ask > $max) {
            return "outside the plausible range {$min}–{$max}";
        }

        return null;
    }

    /** When the last fetch was attempted, and what went wrong if anything. */
    public function status(): array
    {
        $at = setting('spot_last_attempt_at');

        return [
            'attempted_at' => $at ? Carbon::parse($at) : null,
            'error' => setting('spot_last_error') ?: null,
        ];
    }
}
