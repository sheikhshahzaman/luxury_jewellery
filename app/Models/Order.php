<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    public const PAYMENT_STATUSES = ['submitted', 'verified', 'rejected'];

    protected $fillable = [
        'order_number', 'user_id', 'status', 'payment_status', 'bank_account_id',
        'payment_screenshot', 'payment_reference', 'payment_note', 'payment_verified_at',
        'subtotal', 'discount', 'coupon_code', 'shipping_fee', 'tax', 'total',
        'ship_name', 'ship_phone', 'ship_address', 'ship_city', 'ship_state',
        'ship_postal', 'ship_country', 'order_note', 'tracking_number', 'admin_note',
        'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'payment_verified_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (! $order->order_number) {
                do {
                    $number = 'ELX-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(5));
                } while (static::where('order_number', $number)->exists());

                $order->order_number = $number;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function isCancellable(): bool
    {
        return $this->status === 'pending';
    }

    public function canResubmitPayment(): bool
    {
        return $this->payment_status === 'rejected' && $this->status !== 'cancelled';
    }

    /** Restore stock for every item (used on cancellation). */
    public function restoreStock(): void
    {
        foreach ($this->items as $item) {
            if ($item->product) {
                $item->product->increment('stock', $item->quantity);
            }
        }
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'submitted' => 'Awaiting verification',
            'verified' => 'Payment verified',
            'rejected' => 'Payment rejected',
            default => ucfirst($this->payment_status),
        };
    }
}
