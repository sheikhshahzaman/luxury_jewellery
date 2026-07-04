<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::with('category')
            ->search($request->query('q'))
            ->when($request->query('category'), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->query('stock') === 'low', fn ($q) => $q->where('stock', '<=', 5))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product,
            'categories' => Category::ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        if ($request->hasFile('image_file')) {
            $data['image'] = $request->file('image_file')->store('products', 'public');
        }

        $product = Product::create($data);
        $this->storeGalleryImages($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Product "'.$product->name.'" created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product->load('images'),
            'categories' => Category::ordered()->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data['name'] !== $product->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $product->id);
        }

        if ($request->hasFile('image_file')) {
            $this->deleteLocalFile($product->image);
            $data['image'] = $request->file('image_file')->store('products', 'public');
        }

        $product->update($data);
        $this->storeGalleryImages($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Product "'.$product->name.'" updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->deleteLocalFile($product->image);
        foreach ($product->images as $image) {
            $this->deleteLocalFile($image->path);
        }

        $name = $product->name;
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product "'.$name.'" deleted.');
    }

    public function destroyImage(ProductImage $image): RedirectResponse
    {
        $this->deleteLocalFile($image->path);
        $image->delete();

        return back()->with('success', 'Image removed.');
    }

    // ------------------------------------------------------------------

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
            'details' => ['nullable', 'string', 'max:8000'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'stock' => ['required', 'integer', 'min:0'],
            'tag' => ['nullable', 'string', 'max:40'],
            'image' => ['nullable', 'url', 'max:1000'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['sale_price'] = $data['sale_price'] ?? null;
        unset($data['image_file']);

        // Keep existing image when neither URL nor file is provided.
        if (empty($data['image'])) {
            unset($data['image']);
        }

        return $data;
    }

    protected function storeGalleryImages(Request $request, Product $product): void
    {
        $request->validate([
            'gallery' => ['nullable', 'array', 'max:8'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        foreach ($request->file('gallery', []) as $i => $file) {
            $product->images()->create([
                'path' => $file->store('products', 'public'),
                'sort_order' => $product->images()->count() + $i,
            ]);
        }
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $n = 2;

        while (Product::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    protected function deleteLocalFile(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://'])) {
            Storage::disk('public')->delete($path);
        }
    }
}
