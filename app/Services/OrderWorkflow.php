<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Every order status / payment change goes through here so that stock and
 * coupon usage stay consistent. Each method re-reads the order under a lock,
 * so a double-click (or admin + customer at once) can't apply a change twice.
 *
 * Methods throw DomainException with a user-facing message when the change
 * is not allowed.
 */
class OrderWorkflow
{
    public function changeStatus(Order $order, string $status): Order
    {
        return DB::transaction(function () use ($order, $status) {
            $order = $this->lock($order);

            if ($error = $order->transitionError($status)) {
                throw new DomainException($error);
            }

            $this->applyStatus($order, $status);

            return $order;
        });
    }

    /** Customer-initiated cancel: only while the order is still pending. */
    public function cancelByCustomer(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);

            if (! $order->isCancellable()) {
                throw new DomainException('This order can no longer be cancelled.');
            }

            $this->applyStatus($order, 'cancelled');

            return $order;
        });
    }

    public function verifyPayment(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);

            if ($order->payment_status === 'verified') {
                throw new DomainException('Payment is already verified.');
            }
            if ($order->status === 'cancelled') {
                throw new DomainException('This order is cancelled — reactivate it before verifying payment.');
            }

            $order->fill([
                'payment_status' => 'verified',
                'payment_verified_at' => now(),
                'payment_note' => null,
            ]);

            if ($order->status === 'pending') {
                $order->status = 'processing';
            }
            $order->save();

            return $order;
        });
    }

    public function rejectPayment(Order $order, string $note): Order
    {
        return DB::transaction(function () use ($order, $note) {
            $order = $this->lock($order);

            if ($order->isCod()) {
                throw new DomainException('Cash on delivery orders have no payment proof to reject.');
            }
            if ($order->status === 'cancelled') {
                throw new DomainException('This order is cancelled.');
            }
            if ($order->payment_status === 'verified' && $order->status !== 'pending') {
                throw new DomainException('This order is already being fulfilled — move it back to pending first.');
            }

            $order->update([
                'payment_status' => 'rejected',
                'payment_verified_at' => null,
                'payment_note' => $note,
            ]);

            return $order;
        });
    }

    // ------------------------------------------------------------------

    protected function lock(Order $order): Order
    {
        return Order::with('items.product')->lockForUpdate()->findOrFail($order->id);
    }

    protected function applyStatus(Order $order, string $status): void
    {
        $old = $order->status;
        if ($old === $status) {
            return;
        }

        if ($status === 'cancelled') {
            $order->restoreStock();
            $this->adjustCoupon($order, -1);
            $order->cancelled_at = now();
        } elseif ($old === 'cancelled') {
            // Re-activating takes the stock (and the coupon use) again.
            foreach ($order->items as $item) {
                $product = $item->product_id ? Product::lockForUpdate()->find($item->product_id) : null;
                if ($product && $product->stock < $item->quantity) {
                    throw new DomainException('Not enough stock to reactivate this order ('.$item->product_name.').');
                }
            }
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                }
            }
            $this->adjustCoupon($order, +1);
            $order->cancelled_at = null;
        }

        if ($status === 'shipped') {
            $order->shipped_at ??= now();
        } elseif (in_array($status, ['pending', 'processing'], true)) {
            $order->shipped_at = null;
        }

        if ($status === 'delivered') {
            $order->delivered_at ??= now();

            // Cash on delivery is collected by the courier on arrival.
            if ($order->isCod() && $order->payment_status !== 'verified') {
                $order->payment_status = 'verified';
                $order->payment_verified_at = now();
            }
        }

        $order->status = $status;
        $order->save();
    }

    protected function adjustCoupon(Order $order, int $delta): void
    {
        if (! $order->coupon_code) {
            return;
        }

        $query = Coupon::where('code', $order->coupon_code);

        $delta > 0
            ? $query->increment('used_count', $delta)
            : $query->where('used_count', '>', 0)->decrement('used_count', -$delta);
    }
}
