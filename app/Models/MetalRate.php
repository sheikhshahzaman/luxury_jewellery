<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * One of the store's own buy/sell prices (e.g. "Silver — 10 Tola (QR Packaging)").
 *
 * A "manual" item shows exactly the buy/sell typed in Admin. An "auto" item is
 * worked out from spot each time it is shown (LiveRates), using its weight and
 * its buy/sell margins; its stored buy/sell are only the fallback for when
 * there is no spot price or dollar rate. The spot fetch never writes here.
 */
class MetalRate extends Model
{
    public const METALS = [
        'gold' => 'Gold — 24K',
        'silver' => 'Silver (XAG)',
    ];

    public const UNITS = [
        'tola' => 'Tola',
        'gram' => 'Gram',
        'kg' => 'KG',
        'oz' => 'Oz',
    ];

    public const PRICING = [
        'manual' => 'Manual',
        'auto' => 'Auto',
    ];

    /** The spot instrument each metal is priced from. */
    public const INSTRUMENT = ['gold' => 'XAU', 'silver' => 'XAG'];

    protected $attributes = ['pricing' => 'manual', 'buy_margin' => 0, 'sell_margin' => 0];

    protected $fillable = ['key', 'metal', 'label', 'note', 'unit', 'quantity', 'pricing', 'buy_margin', 'sell_margin', 'buy', 'sell', 'sort_order', 'is_active', 'updated_by'];

    protected $casts = [
        'buy' => 'decimal:2',
        'sell' => 'decimal:2',
        'quantity' => 'decimal:3',
        'buy_margin' => 'float',
        'sell_margin' => 'float',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(MetalRateChange::class);
    }

    public function latestChange(): HasOne
    {
        return $this->hasOne(MetalRateChange::class)->latestOfMany();
    }

    public function isAuto(): bool
    {
        return $this->pricing === 'auto';
    }

    /** The item's weight in grams: "10 Tola" → 116.638. */
    public function grams(): float
    {
        return self::gramsFor($this->unit, (float) $this->quantity);
    }

    public static function gramsFor(string $unit, float $quantity): float
    {
        return $quantity * match ($unit) {
            'gram' => 1.0,
            'kg' => 1000.0,
            'oz' => (float) config('rates.grams_per_troy_ounce'),
            default => (float) config('rates.grams_per_tola'),
        };
    }

    /** "Silver — 10 Tola (QR Packaging)" */
    public function displayLabel(): string
    {
        return ucfirst($this->metal).' — '.$this->label.($this->note ? " ({$this->note})" : '');
    }

    /**
     * Quantity and unit from a size label: "10 Tola" → [10, 'tola'], "1 KG" → [1, 'kg'].
     *
     * @return array{0: float, 1: string}
     */
    public static function parseSize(string $label): array
    {
        $quantity = preg_match('/(\d+(?:\.\d+)?)/', $label, $m) ? (float) $m[1] : 1.0;
        $text = strtolower($label);
        $unit = match (true) {
            str_contains($text, 'kg') || str_contains($text, 'kilo') => 'kg',
            str_contains($text, 'gram') || preg_match('/\d\s*g\b/', $text) === 1 => 'gram',
            str_contains($text, 'oz') || str_contains($text, 'ounce') => 'oz',
            default => 'tola',
        };

        return [$quantity, $unit];
    }

    /**
     * A stable key such as "silver_10_tola_qr" or "gold_1_tola", unique among $taken.
     *
     * @param  array<int, string>  $taken
     */
    public static function makeKey(string $metal, float|string $quantity, string $unit, ?string $note, array $taken = []): string
    {
        $qty = rtrim(rtrim(number_format((float) $quantity, 3, '.', ''), '0'), '.');
        $note = trim((string) $note);
        // Packaging notes shorten to their first word: "QR Packaging" → "qr".
        $noteSlug = $note === '' ? '' : Str::slug(preg_replace('/\s+packaging$/i', '', $note), '_');

        $base = Str::slug(implode(' ', array_filter([$metal, str_replace('.', '_', $qty), $unit, $noteSlug])), '_');
        $key = $base;
        for ($i = 2; in_array($key, $taken, true); $i++) {
            $key = "{$base}_{$i}";
        }

        return $key;
    }
}
