<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Testimonial;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $categories = Category::active()->ordered()
            ->withCount(['products as count' => fn ($q) => $q->where('is_active', true)])
            ->take(8)
            ->get();

        $products = Product::active()->featured()->with('category')->latest()->take(6)->get();
        if ($products->count() < 6) {
            $products = $products->concat(
                Product::active()->whereNotIn('id', $products->pluck('id'))->latest()->take(6 - $products->count())->get()
            );
        }

        $testimonials = Testimonial::active()->ordered()->take(12)->get();

        $gallery = collect(json_decode(setting('gallery_images', '[]'), true) ?: [])
            ->filter()->take(5)->values()->all();

        return view('pages.home', compact('categories', 'products', 'testimonials', 'gallery'));
    }
}
