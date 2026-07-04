<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasPurchased($product)) {
            return back()->with('error', 'Only verified buyers can review this piece.');
        }

        if ($product->reviews()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'You have already reviewed this piece.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $product->reviews()->create([
            'user_id' => $user->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'is_approved' => false,
        ]);

        return back()->with('success', 'Thank you — your review is awaiting approval.');
    }
}
