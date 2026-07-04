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

        $qty = max(1, (int) $request->input('qty', 1));

        if ($product->stock <= 0) {
            return back()->with('error', 'This piece is currently out of stock.');
        }

        $already = (int) ($this->cart->raw()[$product->id] ?? 0);
        if ($already + $qty > $product->stock) {
            $qty = max(0, $product->stock - $already);
            if ($qty === 0) {
                return back()->with('error', 'You already have the maximum available quantity of this piece in your cart.');
            }
        }

        $this->cart->add($product, $qty);

        return back()->with('success', $product->name.' added to your cart.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $qty = (int) $request->input('qty', 1);

        if ($qty > $product->stock) {
            $qty = $product->stock;
            $this->cart->update($product, $qty);

            return redirect()->route('cart.index')->with('error', 'Only '.$product->stock.' of this piece are available.');
        }

        $this->cart->update($product, $qty);

        return redirect()->route('cart.index');
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->cart->remove($product->id);

        return redirect()->route('cart.index')->with('success', $product->name.' removed from your cart.');
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
