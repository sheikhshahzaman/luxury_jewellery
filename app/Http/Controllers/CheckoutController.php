<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CheckoutController extends Controller
{
    public function __construct(protected CartService $cart)
    {
    }

    public function show(): View|RedirectResponse
    {
        $summary = $this->cart->summary();

        if ($summary['items']->isEmpty()) {
            return redirect()->route('cart.index')->with('info', 'Your cart is empty.');
        }

        $bankAccounts = BankAccount::active()->ordered()->get();
        $defaultAddress = auth()->user()->addresses()->orderByDesc('is_default')->first();

        return view('checkout.index', $summary + [
            'bankAccounts' => $bankAccounts,
            'defaultAddress' => $defaultAddress,
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        $summary = $this->cart->summary();

        if ($summary['items']->isEmpty()) {
            return redirect()->route('cart.index')->with('info', 'Your cart is empty.');
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
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'save_address' => ['nullable', 'boolean'],
        ], [
            'payment_screenshot.required' => 'Please upload a screenshot of your bank transfer to finalise the order.',
            'bank_account_id.required' => 'Please select the bank account you transferred to.',
        ]);

        $bankAccount = BankAccount::active()->find($data['bank_account_id']);
        if (! $bankAccount) {
            return back()->withInput()->with('error', 'The selected bank account is no longer available.');
        }

        try {
            $order = DB::transaction(function () use ($data, $summary, $bankAccount, $request) {
                // Re-check stock inside the transaction.
                foreach ($summary['items'] as $line) {
                    $fresh = Product::lockForUpdate()->find($line['product']->id);
                    if (! $fresh || ! $fresh->is_active || $fresh->stock < $line['qty']) {
                        throw new \RuntimeException(($fresh?->name ?? 'A piece').' no longer has enough stock. Please review your cart.');
                    }
                }

                $screenshotPath = $request->file('payment_screenshot')->store('payments');

                $order = Order::create([
                    'user_id' => auth()->id(),
                    'status' => 'pending',
                    'payment_status' => 'submitted',
                    'bank_account_id' => $bankAccount->id,
                    'payment_screenshot' => $screenshotPath,
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'subtotal' => $summary['subtotal'],
                    'discount' => $summary['discount'],
                    'coupon_code' => $summary['coupon']?->code,
                    'shipping_fee' => $summary['shipping'],
                    'tax' => $summary['tax'],
                    'total' => $summary['total'],
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
                        'product_image' => $line['product']->image,
                        'price' => $line['product']->current_price,
                        'quantity' => $line['qty'],
                        'line_total' => $line['line_total'],
                    ]);
                    Product::where('id', $line['product']->id)->decrement('stock', $line['qty']);
                }

                $summary['coupon']?->increment('used_count');

                return $order;
            });
        } catch (\RuntimeException $e) {
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

    /** Serve the private payment screenshot to its owner or an admin. */
    public function screenshot(Order $order): BinaryFileResponse
    {
        abort_unless(auth()->check() && ($order->user_id === auth()->id() || auth()->user()->isAdmin()), 403);
        abort_unless($order->payment_screenshot && Storage::disk('local')->exists($order->payment_screenshot), 404);

        return response()->file(Storage::disk('local')->path($order->payment_screenshot));
    }
}
