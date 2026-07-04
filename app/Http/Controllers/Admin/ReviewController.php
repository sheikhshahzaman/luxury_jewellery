<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $reviews = Review::with(['product', 'user'])
            ->when($request->query('status') === 'pending', fn ($q) => $q->where('is_approved', false))
            ->when($request->query('status') === 'approved', fn ($q) => $q->where('is_approved', true))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.reviews.index', compact('reviews'));
    }

    public function approve(Review $review): RedirectResponse
    {
        $review->update(['is_approved' => true]);
        $review->product?->refreshRating();

        return back()->with('success', 'Review approved and published.');
    }

    public function unapprove(Review $review): RedirectResponse
    {
        $review->update(['is_approved' => false]);
        $review->product?->refreshRating();

        return back()->with('success', 'Review hidden.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $product = $review->product;
        $review->delete();
        $product?->refreshRating();

        return back()->with('success', 'Review deleted.');
    }
}
