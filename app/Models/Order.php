<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    /** "unpaid" is used for cash on delivery until the cash is collected. */
    public const PAYMENT_STATUSES = ['submitted', 'verified', 'rejected', 'unpaid'];

    public const PAYMENT_METHODS = [
        'bank' => 'Bank transfer',
        'jazzcash' => 'JazzCash',
        'easypaisa' => 'Easypaisa',
        'cod' => 'Cash on delivery',
    ];

    /** Which status an order may move to from each status. */
    public const TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['pending', 'shipped', 'cancelled'],
        'shipped' => ['processing', 'delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => ['pending'],
    ];

    protected $fillable = [
        'order_number', 'user_id', 'status', 'payment_status', 'payment_method', 'bank_account_id',
        'payment_screenshot', 'payment_reference', 'payer_name', 'payment_note', 'payment_verified_at',
        'subtotal', 'discount', 'coupon_code', 'shipping_fee', 'tax', 'payment_fee', 'total',
        'ship_name', 'ship_phone', 'ship_address', 'ship_city', 'ship_state',
        'ship_postal', 'ship_country', 'order_note', 'tracking_number', 'admin_note',
        'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'tax' => 'decimal:2',
        'payment_fee' => 'decimal:2',
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
                    $number = 'LAX-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(5));
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
        return ! $this->isCod() && $this->payment_status === 'rejected' && $this->status !== 'cancelled';
    }

    public function isCod(): bool
    {
        return $this->payment_method === 'cod';
    }

    /** Payment can be verified (or COD marked collected) while the order is live. */
    public function canVerifyPayment(): bool
    {
        return $this->status !== 'cancelled' && $this->payment_status !== 'verified';
    }

    /** Statuses the admin may pick from the current one (current status first). */
    public function nextStatuses(): array
    {
        return array_merge([$this->status], self::TRANSITIONS[$this->status] ?? []);
    }

    /**
     * Why this order can't move to $status, or null when it can.
     */
    public function transitionError(string $status): ?string
    {
        if ($status === $this->status) {
            return null;
        }

        if (! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            return 'A '.$this->status.' order cannot be moved to '.$status.'.';
        }

        $needsPayment = in_array($status, ['processing', 'shipped', 'delivered'], true);
        if ($needsPayment && ! $this->isCod() && $this->payment_status !== 'verified') {
            return 'Verify the payment before moving this order to '.$status.'.';
        }

        return null;
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
            'verified' => $this->isCod() ? 'Cash collected' : 'Payment verified',
            'rejected' => 'Payment rejected',
            'unpaid' => 'Pay on delivery',
            default => ucfirst((string) $this->payment_status),
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? ucfirst((string) $this->payment_method);
    }
}
