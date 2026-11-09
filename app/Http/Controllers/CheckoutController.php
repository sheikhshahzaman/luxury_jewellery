<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CheckoutController extends Controller
{
    protected ?Collection $accountsCache = null;

    public function __construct(protected CartService $cart)
    {
    }

    public function show(): View|RedirectResponse
    {
        $summary = $this->cart->summary();

        if ($summary['items']->isEmpty()) {
            return redirect()->route('cart.index')->with('info', 'Your cart is empty.');
        }

        $defaultAddress = auth()->user()->addresses()->orderByDesc('is_default')->first();

        return view('checkout.index', $summary + [
            'accountsByMethod' => $this->accountsByMethod(),
            'methods' => $this->availableMethods(),
            'codFee' => CartService::codFee(),
            'defaultAddress' => $defaultAddress,
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        $summary = $this->cart->summary();

        if ($summary['items']->isEmpty()) {
            return redirect()->route('cart.index')->with('info', 'Your cart is empty.');
        }

        $methods = $this->availableMethods();
        if ($methods === []) {
            return back()->withInput()->with('error', 'Checkout is temporarily unavailable — no payment method is configured.');
        }

        $data = $request->validate([
            'ship_name' => ['required', 'string', 'max:255'],
            'ship_phone' => ['required', 'string', 'max:30'],
            'ship_address' => ['required', 'string', 'max:500'],
            'ship_city' => ['required', 'string', 'max:120'],
            'ship_state' => ['nullable', 'string', 'max:120'],
            'ship_postal' => ['nullable', 'string', 'max:30'],
            'ship_country' => ['required', 'string', 'max:120'],
            'order_note' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', Rule::in(array_keys($methods))],
            'bank_account_id' => ['exclude_if:payment_method,cod', 'required', 'integer'],
            'payment_reference' => ['exclude_if:payment_method,cod', 'required', 'string', 'max:120'],
            'payer_name' => ['exclude_if:payment_method,cod', 'required', 'string', 'max:255'],
            'payment_screenshot' => ['exclude_if:payment_method,cod', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'save_address' => ['nullable', 'boolean'],
        ], [
            'payment_method.required' => 'Please choose how you would like to pay.',
            'payment_method.in' => 'That payment method is not available.',
            'payment_screenshot.required' => 'Please upload a screenshot of your payment receipt to finalise the order.',
            'bank_account_id.required' => 'Please select the account you sent the payment to.',
            'payment_reference.required' => 'Please enter the transaction ID from your receipt.',
            'payer_name.required' => 'Please enter the name the payment was sent from.',
        ]);

        $isCod = $data['payment_method'] === 'cod';
        $account = null;

        if (! $isCod) {
            $account = BankAccount::active()->where('type', $data['payment_method'])->find($data['bank_account_id']);
            if (! $account) {
                return back()->withInput()->withErrors(['bank_account_id' => 'The selected account is no longer available — please choose another.']);
            }
        }

        $paymentFee = $isCod ? CartService::codFee() : 0.0;
        $screenshotPath = $isCod ? null : $request->file('payment_screenshot')->store('payments');

        try {
            $order = DB::transaction(function () use ($data, $summary, $account, $isCod, $paymentFee, $screenshotPath) {
                // Re-check stock inside the transaction (all option lines of a product share its stock).
                foreach ($summary['items']->groupBy(fn ($line) => $line['product']->id) as $productId => $lines) {
                    $fresh = Product::lockForUpdate()->find($productId);
                    if (! $fresh || ! $fresh->is_active || $fresh->stock < $lines->sum('qty')) {
                        throw new \RuntimeException(($fresh?->name ?? 'A piece').' no longer has enough stock. Please review your cart.');
                    }
                }

                // Re-check the coupon so two orders can't both take its last use.
                if ($summary['coupon']) {
                    $coupon = Coupon::lockForUpdate()->find($summary['coupon']->id);
                    if (! $coupon || $coupon->validationError($summary['subtotal']) !== null) {
                        throw new \RuntimeException('Coupon '.$summary['coupon']->code.' can no longer be applied. Please review your cart.');
                    }
                    $coupon->increment('used_count');
                }

                $order = Order::create([
                    'user_id' => auth()->id(),
                    'status' => 'pending',
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $isCod ? 'unpaid' : 'submitted',
                    'bank_account_id' => $account?->id,
                    'payment_screenshot' => $screenshotPath,
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'payer_name' => $data['payer_name'] ?? null,
                    'subtotal' => $summary['subtotal'],
                    'discount' => $summary['discount'],
                    'coupon_code' => $summary['coupon']?->code,
                    'shipping_fee' => $summary['shipping'],
                    'tax' => $summary['tax'],
                    'payment_fee' => $paymentFee,
                    'total' => round($summary['total'] + $paymentFee, 2),
                    'ship_name' => $data['ship_name'],
                    'ship_phone' => $data['ship_phone'],
                    'ship_address' => $data['ship_address'],
                    'ship_city' => $data['ship_city'],
                    'ship_state' => $data['ship_state'] ?? null,
                    'ship_postal' => $data['ship_postal'] ?? null,
                    'ship_country' => $data['ship_country'],
                    'order_note' => $data['order_note'] ?? null,
                ]);

                foreach ($summary['items'] as $line) {
                    $order->items()->create([
                        'product_id' => $line['product']->id,
                        'product_name' => $line['product']->name,
                        'product_image' => $this->snapshotImage($line['product']->image),
                        'options' => $line['options'] ?: null,
                        'price' => $line['unit_price'],
                        'quantity' => $line['qty'],
                        'line_total' => $line['line_total'],
                    ]);
                    Product::where('id', $line['product']->id)->decrement('stock', $line['qty']);
                }

                return $order;
            });
        } catch (\RuntimeException $e) {
            if ($screenshotPath) {
                Storage::disk('local')->delete($screenshotPath);
            }

            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        if ($request->boolean('save_address')) {
            auth()->user()->addresses()->firstOrCreate([
                'address' => $data['ship_address'],
                'city' => $data['ship_city'],
            ], [
                'label' => 'Saved at checkout',
                'name' => $data['ship_name'],
                'phone' => $data['ship_phone'],
                'state' => $data['ship_state'] ?? null,
                'postal_code' => $data['ship_postal'] ?? null,
                'country' => $data['ship_country'],
            ]);
        }

        $this->cart->clear();

        return redirect()->route('checkout.confirmation', $order);
    }

    public function confirmation(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $order->load(['items', 'bankAccount']);

        return view('checkout.success', compact('order'));
    }

    /**
     * Payment methods offered at checkout: each wallet/bank type with at
     * least one active account, plus cash on delivery when enabled.
     *
     * @return array<string, string> method => label
     */
    protected function availableMethods(): array
    {
        $types = $this->accountsByMethod()->keys()->all();

        $methods = collect(Order::PAYMENT_METHODS)
            ->filter(fn ($label, $method) => in_array($method, $types, true))
            ->all();

        if (CartService::codEnabled()) {
            $methods['cod'] = Order::PAYMENT_METHODS['cod'];
        }

        return $methods;
    }

    protected function accountsByMethod(): Collection
    {
        return $this->accountsCache ??= BankAccount::active()->ordered()->get()->groupBy('type');
    }

    /**
     * Copy an uploaded product image for the order line, so later edits or
     * deletion of the product don't break past orders. URLs are kept as-is.
     */
    protected function snapshotImage(?string $path): ?string
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return $path;
        }

        $copy = 'order-items/'.Str::random(32).'.'.pathinfo($path, PATHINFO_EXTENSION);
        $disk->copy($path, $copy);

        return $copy;
    }

    /** Serve the private payment screenshot to its owner or an admin. */
    public function screenshot(Order $order): BinaryFileResponse
    {
        abort_unless(auth()->check() && ($order->user_id === auth()->id() || auth()->user()->isAdmin()), 403);
        abort_unless($order->payment_screenshot && Storage::disk('local')->exists($order->payment_screenshot), 404);

        return response()->file(Storage::disk('local')->path($order->payment_screenshot));
    }
}
