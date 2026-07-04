<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_order', 'max_uses', 'used_count',
        'starts_at', 'expires_at', 'is_active', 'is_public',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    /** Coupons that are live right now and safe to advertise publicly. */
    public function scopePubliclyListable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true)
            ->where('is_public', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));
    }

    /**
     * Check whether this coupon can be applied to a cart subtotal.
     * Returns an error message, or null when valid.
     */
    public function validationError(float $subtotal): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is no longer active.';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'This coupon is not active yet.';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'This coupon has expired.';
        }
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return 'This coupon has reached its usage limit.';
        }
        if ($this->min_order !== null && $subtotal < (float) $this->min_order) {
            return 'This coupon requires a minimum order of '.price_format($this->min_order).'.';
        }

        return null;
    }

    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === 'percent'
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min($discount, $subtotal), 2);
    }

    public function getSummaryAttribute(): string
    {
        return $this->type === 'percent'
            ? rtrim(rtrim((string) $this->value, '0'), '.').'% off'
            : price_format($this->value).' off';
    }
}
