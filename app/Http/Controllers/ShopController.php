<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    /** Live search suggestions for the header search bar. */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $results = Product::active()->with('category')->search($term)
            ->orderByDesc('is_featured')
            ->take(6)
            ->get()
            ->map(fn (Product $p) => [
                'name' => $p->name,
                'category' => $p->category?->name,
                'price' => price_format($p->current_price),
                'image' => $p->image_url,
                'url' => route('product.show', $p),
            ]);

        return response()->json(['results' => $results]);
    }

    public function index(Request $request): View
    {
        $categories = Category::active()->ordered()
            ->withCount(['products as count' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $activeCategory = null;
        if ($request->filled('category')) {
            $activeCategory = $categories->firstWhere('slug', $request->query('category'));
        }

        $query = Product::active()->with('category')
            ->search($request->query('q'));

        if ($activeCategory) {
            $query->where('category_id', $activeCategory->id);
        }

        // Filter on the effective (sale) price.
        if (is_numeric($request->query('min'))) {
            $query->whereRaw(Product::EFFECTIVE_PRICE_SQL.' >= ?', [(float) $request->query('min')]);
        }
        if (is_numeric($request->query('max'))) {
            $query->whereRaw(Product::EFFECTIVE_PRICE_SQL.' <= ?', [(float) $request->query('max')]);
        }

        match ($request->query('sort')) {
            'price_asc' => $query->orderByRaw(Product::EFFECTIVE_PRICE_SQL.' asc'),
            'price_desc' => $query->orderByRaw(Product::EFFECTIVE_PRICE_SQL.' desc'),
            'name' => $query->orderBy('name'),
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('reviews_count'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();

        return view('shop.index', [
            'products' => $products,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $product->load(['category', 'images']);

        $reviews = $product->approvedReviews()->with('user')->latest()->get();

        $related = Product::active()
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->take(3)
            ->get();

        $canReview = false;
        $hasReviewed = false;
        if (auth()->check()) {
            $hasReviewed = $product->reviews()->where('user_id', auth()->id())->exists();
            $canReview = ! $hasReviewed && auth()->user()->hasPurchased($product);
        }

        return view('shop.show', compact('product', 'reviews', 'related', 'canReview', 'hasReviewed'));
    }
}
