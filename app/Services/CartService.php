<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Session based shopping cart.
 *
 * Session shape: cart => [product_id => quantity], coupon_code => string|null
 */
class CartService
{
    protected const CART_KEY = 'cart';

    protected const COUPON_KEY = 'coupon_code';

    /** Raw [product_id => qty] map. */
    public function raw(): array
    {
        return session(self::CART_KEY, []);
    }

    /**
     * Hydrated cart rows. Silently drops products that were
     * deleted or deactivated since being added.
     *
     * @return Collection<int, array{product: Product, qty: int, line_total: float}>
     */
    public function items(): Collection
    {
        $raw = $this->raw();
        if (empty($raw)) {
            return collect();
        }

        $products = Product::with('category')
            ->where('is_active', true)
            ->whereIn('id', array_keys($raw))
            ->get()
            ->keyBy('id');

        $items = collect();
        $dirty = false;

        foreach ($raw as $id => $qty) {
            $product = $products->get($id);
            if (! $product || $product->stock <= 0) {
                unset($raw[$id]);
                $dirty = true;

                continue;
            }
            $qty = max(1, min((int) $qty, $product->stock));
            $items->push([
                'product' => $product,
                'qty' => $qty,
                'line_total' => round($product->current_price * $qty, 2),
            ]);
        }

        if ($dirty) {
            session([self::CART_KEY => $raw]);
        }

        return $items;
    }

    public function add(Product $product, int $qty = 1): void
    {
        $raw = $this->raw();
        $current = (int) ($raw[$product->id] ?? 0);
        $raw[$product->id] = min($current + max(1, $qty), $product->stock);
        session([self::CART_KEY => $raw]);
    }

    public function update(Product $product, int $qty): void
    {
        $raw = $this->raw();
        if ($qty <= 0) {
            unset($raw[$product->id]);
        } else {
            $raw[$product->id] = min($qty, $product->stock);
        }
        session([self::CART_KEY => $raw]);
    }

    public function remove(int $productId): void
    {
        $raw = $this->raw();
        unset($raw[$productId]);
        session([self::CART_KEY => $raw]);
    }

    public function clear(): void
    {
        session()->forget([self::CART_KEY, self::COUPON_KEY]);
    }

    public function count(): int
    {
        return array_sum(array_map('intval', $this->raw()));
    }

    public function subtotal(): float
    {
        return round($this->items()->sum('line_total'), 2);
    }

    // ------------------------------------------------------------------
    // Coupon
    // ------------------------------------------------------------------

    public function applyCoupon(string $code): void
    {
        session([self::COUPON_KEY => strtoupper(trim($code))]);
    }

    public function removeCoupon(): void
    {
        session()->forget(self::COUPON_KEY);
    }

    /** The applied coupon, revalidated against the current subtotal. */
    public function coupon(): ?Coupon
    {
        $code = session(self::COUPON_KEY);
        if (! $code) {
            return null;
        }

        $coupon = Coupon::where('code', $code)->first();
        if (! $coupon || $coupon->validationError($this->subtotal()) !== null) {
            $this->removeCoupon();

            return null;
        }

        return $coupon;
    }

    // ------------------------------------------------------------------
    // Totals
    // ------------------------------------------------------------------

    public function discount(): float
    {
        $coupon = $this->coupon();

        return $coupon ? $coupon->discountFor($this->subtotal()) : 0.0;
    }

    public function shippingFee(): float
    {
        $subtotal = $this->subtotal();
        if ($subtotal <= 0) {
            return 0.0;
        }

        $freeMin = (float) setting('free_shipping_min', 150);
        if ($freeMin > 0 && $subtotal >= $freeMin) {
            return 0.0;
        }

        return round((float) setting('shipping_fee', 15), 2);
    }

    public function tax(): float
    {
        $rate = (float) setting('tax_rate', 0);
        if ($rate <= 0) {
            return 0.0;
        }

        return round(($this->subtotal() - $this->discount()) * $rate / 100, 2);
    }

    public function total(): float
    {
        return round($this->subtotal() - $this->discount() + $this->shippingFee() + $this->tax(), 2);
    }

    /** Everything the cart/checkout pages need in one call. */
    public function summary(): array
    {
        $items = $this->items();
        $subtotal = round($items->sum('line_total'), 2);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'coupon' => $this->coupon(),
            'discount' => $this->discount(),
            'shipping' => $this->shippingFee(),
            'tax' => $this->tax(),
            'total' => $this->total(),
        ];
    }
}
