<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(protected CartService $cart)
    {
    }

    public function index(): View
    {
        return view('cart.index', $this->cart->summary());
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        if ($product->stock <= 0) {
            return back()->with('error', 'This piece is currently out of stock.');
        }

        // Pieces with a metal or size must be configured on the product page.
        if ($product->requiresChoice() && ! $request->hasAny(['metal', 'size'])) {
            return redirect()->route('product.show', $product)
                ->with('info', 'Choose your options for '.$product->name.' first.');
        }

        $request->validate(['qty' => ['nullable', 'integer', 'min:1', 'max:99']]);
        $options = $product->resolveOptions($request->only(['metal', 'size', 'engraving']));

        $added = $this->cart->add($product, (int) $request->input('qty', 1), $options);

        if ($added === 0) {
            return back()->with('error', 'You already have the maximum available quantity of this piece in your cart.');
        }

        return back()->with('success', $product->name.' added to your cart.');
    }

    public function update(Request $request, string $line): RedirectResponse
    {
        $wanted = (int) $request->input('qty', 1);
        $kept = $this->cart->update($line, $wanted);

        if ($kept === null) {
            return redirect()->route('cart.index')->with('error', 'That item is no longer in your cart.');
        }

        if ($kept < $wanted) {
            return redirect()->route('cart.index')->with('error', $kept > 0
                ? 'Quantity set to '.$kept.' — that is all we have in stock.'
                : 'This piece is no longer available and was removed.');
        }

        return redirect()->route('cart.index');
    }

    public function remove(string $line): RedirectResponse
    {
        $raw = $this->cart->line($line);
        $this->cart->remove($line);

        $name = $raw ? Product::find($raw['product_id'])?->name : null;

        return redirect()->route('cart.index')->with('success', ($name ?? 'Item').' removed from your cart.');
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:50']]);

        $code = strtoupper(trim($request->input('code')));
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            return back()->with('error', 'That coupon code is not valid.');
        }

        if ($error = $coupon->validationError($this->cart->subtotal())) {
            return back()->with('error', $error);
        }

        $this->cart->applyCoupon($code);

        return back()->with('success', 'Coupon '.$code.' applied — '.$coupon->summary.'.');
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->removeCoupon();

        return back()->with('success', 'Coupon removed.');
    }
}
