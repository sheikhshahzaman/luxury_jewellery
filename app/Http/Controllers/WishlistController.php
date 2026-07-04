<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;

class WishlistController extends Controller
{
    public function toggle(Product $product): RedirectResponse
    {
        $user = auth()->user();

        // Query the pivot directly (not the request-cached helper) so the
        // decision is always based on current state.
        if ($user->wishlist()->where('product_id', $product->id)->exists()) {
            $user->wishlist()->detach($product->id);

            return back()->with('success', $product->name.' removed from your wishlist.');
        }

        $user->wishlist()->attach($product->id);

        return back()->with('success', $product->name.' added to your wishlist.');
    }
}
