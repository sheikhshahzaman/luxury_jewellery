<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit trail: one row each time an admin changes a buy or sell price. */
class MetalRateChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['metal_rate_id', 'key', 'buy_before', 'sell_before', 'buy', 'sell', 'user_id'];

    protected $casts = [
        'buy_before' => 'decimal:2',
        'sell_before' => 'decimal:2',
        'buy' => 'decimal:2',
        'sell' => 'decimal:2',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(MetalRate::class, 'metal_rate_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
