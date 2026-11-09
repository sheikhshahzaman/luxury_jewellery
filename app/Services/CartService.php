<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Session based shopping cart.
 *
 * Session shape:
 *   cart => [line_key => ['product_id' => int, 'qty' => int, 'options' => array]]
 *   coupon_code => string|null
 *
 * The same product with different options (e.g. two ring sizes) is two lines.
 * Stock is tracked per product, so all lines of one product share its stock.
 */
class CartService
{
    protected const CART_KEY = 'cart';

    protected const COUPON_KEY = 'coupon_code';

    /**
     * Memo of hydrated items, valid only for the exact session contents it
     * was built from (the service can outlive a request, e.g. in tests/Octane).
     */
    protected ?Collection $itemsCache = null;

    protected ?string $itemsCacheFor = null;

    /**
     * Raw cart lines. Upgrades the legacy [product_id => qty] shape on the fly.
     *
     * @return array<string, array{product_id: int, qty: int, options: array}>
     */
    public function raw(): array
    {
        $lines = [];

        foreach (session(self::CART_KEY, []) as $key => $line) {
            if (! is_array($line)) {
                $line = ['product_id' => (int) $key, 'qty' => (int) $line, 'options' => []];
                $key = self::lineKey((int) $key, []);
            }

            $lines[(string) $key] = [
                'product_id' => (int) ($line['product_id'] ?? 0),
                'qty' => (int) ($line['qty'] ?? 0),
                'options' => (array) ($line['options'] ?? []),
            ];
        }

        return $lines;
    }

    public static function lineKey(int $productId, array $options): string
    {
        if ($options === []) {
            return 'p'.$productId;
        }

        ksort($options);

        return 'p'.$productId.'-'.substr(sha1(json_encode($options)), 0, 10);
    }

    /**
     * Hydrated cart rows. Silently drops products that were deleted or
     * deactivated, and clamps quantities to the product's stock.
     *
     * @return Collection<int, array{key: string, product: Product, qty: int, options: array, option_lines: array, unit_price: float, line_total: float}>
     */
    public function items(): Collection
    {
        $fingerprint = serialize(session(self::CART_KEY, []));
        if ($this->itemsCache !== null && $this->itemsCacheFor === $fingerprint) {
            return $this->itemsCache;
        }

        $raw = $this->raw();
        if (empty($raw)) {
            return collect();
        }

        $products = Product::with('category')
            ->where('is_active', true)
            ->whereIn('id', array_unique(array_column($raw, 'product_id')))
            ->get()
            ->keyBy('id');

        $items = collect();
        $stockLeft = [];
        $clean = [];

        foreach ($raw as $key => $line) {
            $product = $products->get($line['product_id']);
            if (! $product) {
                continue;
            }

            $left = $stockLeft[$product->id] ??= (int) $product->stock;
            $qty = min(max(1, $line['qty']), $left);
            if ($qty <= 0) {
                continue;
            }
            $stockLeft[$product->id] -= $qty;

            $unit = $product->unitPriceFor($line['options']);
            $items->push([
                'key' => $key,
                'product' => $product,
                'qty' => $qty,
                'options' => $line['options'],
                'option_lines' => Product::describeOptions($line['options']),
                'unit_price' => $unit,
                'line_total' => round($unit * $qty, 2),
            ]);
            $clean[$key] = ['product_id' => $product->id, 'qty' => $qty, 'options' => $line['options']];
        }

        if ($clean !== session(self::CART_KEY, [])) {
            session([self::CART_KEY => $clean]);
        }

        $this->itemsCacheFor = serialize($clean);

        return $this->itemsCache = $items;
    }

    /** Units of a product already in the cart, across all its option lines. */
    public function quantityOf(int $productId, ?string $exceptKey = null): int
    {
        return collect($this->raw())
            ->filter(fn ($line, $key) => $line['product_id'] === $productId && $key !== $exceptKey)
            ->sum('qty');
    }

    /** Adds up to $qty units; returns how many were actually added. */
    public function add(Product $product, int $qty = 1, array $options = []): int
    {
        $raw = $this->raw();
        $key = self::lineKey($product->id, $options);

        $room = max(0, (int) $product->stock - $this->quantityOf($product->id));
        $added = min(max(1, $qty), $room);
        if ($added <= 0) {
            return 0;
        }

        $raw[$key] = [
            'product_id' => $product->id,
            'qty' => ($raw[$key]['qty'] ?? 0) + $added,
            'options' => $options,
        ];
        $this->write($raw);

        return $added;
    }

    /**
     * Set a line's quantity (0 removes it). Returns the quantity kept,
     * which may be lower than requested when stock is short, or null
     * when the line doesn't exist.
     */
    public function update(string $key, int $qty): ?int
    {
        $raw = $this->raw();
        if (! isset($raw[$key])) {
            return null;
        }

        if ($qty <= 0) {
            unset($raw[$key]);
            $this->write($raw);

            return 0;
        }

        $product = Product::find($raw[$key]['product_id']);
        $room = $product ? max(0, (int) $product->stock - $this->quantityOf($product->id, $key)) : 0;
        $kept = min($qty, $room);

        if ($kept <= 0) {
            unset($raw[$key]);
        } else {
            $raw[$key]['qty'] = $kept;
        }
        $this->write($raw);

        return $kept;
    }

    public function remove(string $key): void
    {
        $raw = $this->raw();
        unset($raw[$key]);
        $this->write($raw);
    }

    /** The raw line for a key, or null. */
    public function line(string $key): ?array
    {
        return $this->raw()[$key] ?? null;
    }

    public function clear(): void
    {
        session()->forget([self::CART_KEY, self::COUPON_KEY]);
        $this->itemsCache = null;
    }

    public function count(): int
    {
        return (int) array_sum(array_column($this->raw(), 'qty'));
    }

    public function subtotal(): float
    {
        return round($this->items()->sum('line_total'), 2);
    }

    protected function write(array $raw): void
    {
        session([self::CART_KEY => $raw]);
        $this->itemsCache = null;
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

    /** Extra fee charged for cash on delivery (0 when COD is off). */
    public static function codFee(): float
    {
        return round(max(0, (float) setting('cod_fee', 0)), 2);
    }

    public static function codEnabled(): bool
    {
        return (string) setting('cod_enabled', '1') === '1';
    }

    /** Everything the cart/checkout pages need in one call. */
    public function summary(): array
    {
        $items = $this->items();

        return [
            'items' => $items,
            'subtotal' => $this->subtotal(),
            'coupon' => $this->coupon(),
            'discount' => $this->discount(),
            'shipping' => $this->shippingFee(),
            'tax' => $this->tax(),
            'total' => $this->total(),
        ];
    }
}
