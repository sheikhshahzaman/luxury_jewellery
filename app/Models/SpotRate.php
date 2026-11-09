<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One successful spot reading for one instrument: XAU and XAG in USD per troy
 * ounce, USDPKR in rupees per dollar. Rows are only ever added, so the newest
 * row per instrument is the current value and older rows are the history.
 */
class SpotRate extends Model
{
    public const INSTRUMENTS = ['XAU', 'XAG', 'USDPKR'];

    public $timestamps = false;

    protected $fillable = ['instrument', 'bid', 'ask', 'source', 'fetched_at'];

    protected $casts = [
        'bid' => 'float',
        'ask' => 'float',
        'fetched_at' => 'datetime',
    ];

    public function scopeFor(Builder $query, string $instrument): Builder
    {
        return $query->where('instrument', $instrument);
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('fetched_at')->orderByDesc('id');
    }

    public function mid(): float
    {
        return ($this->bid + $this->ask) / 2;
    }
}
