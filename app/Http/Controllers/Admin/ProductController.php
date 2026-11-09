<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $data['slug'] = Slug::unique(Product::class, $data['name']);

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
        $data = $this->validated($request, $product);

        if ($data['name'] !== $product->name) {
            $data['slug'] = Slug::unique(Product::class, $data['name'], $product->id);
        }

        if ($request->hasFile('image_file')) {
            $data['image'] = $request->file('image_file')->store('products', 'public');
        }

        // Main image replaced (by an upload or a URL): drop the old file.
        if (isset($data['image']) && $data['image'] !== $product->image) {
            $this->deleteLocalFile($product->image);
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

    protected function validated(Request $request, ?Product $product = null): array
    {
        // Validate everything — gallery included — before anything is saved.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'details' => ['nullable', 'string', 'max:8000'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'stock' => ['required', 'integer', 'min:0'],
            'tag' => ['nullable', 'string', 'max:40'],
            'image' => ['nullable', 'url', 'max:1000'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'gallery' => ['nullable', 'array', 'max:8'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'metal_options' => ['nullable', 'string', 'max:2000'],
            'size_options' => ['nullable', 'string', 'max:1000'],
            'size_label' => ['nullable', 'string', 'max:40'],
            'engraving_enabled' => ['nullable', 'boolean'],
            'engraving_fee' => ['nullable', 'numeric', 'min:0'],
        ], [
            'sku.unique' => 'Another product already uses this SKU.',
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['sale_price'] = $data['sale_price'] ?? null;
        $data['sku'] = ($data['sku'] ?? '') !== '' ? $data['sku'] : null;
        $data['metal_options'] = $this->parseMetals($data['metal_options'] ?? '');
        $data['size_options'] = $this->parseSizes($data['size_options'] ?? '');
        $data['size_label'] = $data['size_label'] ?? null;
        $data['engraving_enabled'] = $request->boolean('engraving_enabled');
        $data['engraving_fee'] = (float) ($data['engraving_fee'] ?? 0);
        unset($data['image_file'], $data['gallery']);

        // Keep existing image when neither URL nor file is provided.
        if (empty($data['image'])) {
            unset($data['image']);
        }

        return $data;
    }

    /**
     * "Yellow gold | 0" per line → [['name' => 'Yellow gold', 'price' => 0.0], …]
     */
    protected function parseMetals(string $input): ?array
    {
        $metals = [];

        foreach (preg_split('/\R/', $input) as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            [$name, $price] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '0');
            $price = str_replace(['+', ' '], '', $price === '' ? '0' : $price);

            if ($name === '' || ! is_numeric($price)) {
                throw ValidationException::withMessages([
                    'metal_options' => 'Line '.($i + 1).' should look like "Rose gold | 20" (name | extra price).',
                ]);
            }

            $metals[] = ['name' => $name, 'price' => round((float) $price, 2)];
        }

        return $metals ?: null;
    }

    /** "5, 6, 7" → ["5", "6", "7"] */
    protected function parseSizes(string $input): ?array
    {
        $sizes = collect(preg_split('/[,\n]/', $input))
            ->map(fn ($s) => trim($s))
            ->filter(fn ($s) => $s !== '')
            ->unique()
            ->values()
            ->all();

        return $sizes ?: null;
    }

    protected function storeGalleryImages(Request $request, Product $product): void
    {
        $offset = $product->images()->count();

        foreach ($request->file('gallery', []) as $i => $file) {
            $product->images()->create([
                'path' => $file->store('products', 'public'),
                'sort_order' => $offset + $i,
            ]);
        }
    }

    /** Delete an uploaded file, unless it's a URL or past orders still show it. */
    protected function deleteLocalFile(?string $path): void
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        if (OrderItem::where('product_image', $path)->exists()) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
