@extends('layouts.admin')

@section('title', $product->exists ? 'Edit product' : 'New product')

@section('content')
<form class="adm-form" method="POST"
      action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div class="adm-panel">
        <h2>Basics</h2>
        <div class="form-grid">
            <div class="field full">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $product->name) }}" required>
            </div>
            <div class="field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="">— None —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="sku">SKU</label>
                <input id="sku" type="text" name="sku" value="{{ old('sku', $product->sku) }}" placeholder="ELX-R-001">
            </div>
            <div class="field full">
                <label for="description">Short description</label>
                <textarea id="description" name="description">{{ old('description', $product->description) }}</textarea>
            </div>
            <div class="field full">
                <label for="details">Details (materials, sizing — line breaks preserved)</label>
                <textarea id="details" name="details" style="min-height:140px">{{ old('details', $product->details) }}</textarea>
            </div>
        </div>
    </div>

    <div class="adm-panel">
        <h2>Pricing & stock</h2>
        <div class="form-grid">
            <div class="field">
                <label for="price">Price</label>
                <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}" required>
            </div>
            <div class="field">
                <label for="sale_price">Sale price (optional, lower than price)</label>
                <input id="sale_price" type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price', $product->sale_price) }}">
            </div>
            <div class="field">
                <label for="stock">Stock</label>
                <input id="stock" type="number" min="0" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" required>
            </div>
            <div class="field">
                <label for="tag">Tag badge (optional)</label>
                <input id="tag" type="text" name="tag" value="{{ old('tag', $product->tag) }}" placeholder="Bestseller, New, Limited…">
            </div>
        </div>
        <label class="switch-row">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
            Featured — show in the "Best sellers" section on the home page
        </label>
        <label class="switch-row">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->exists ? $product->is_active : true))>
            Active — visible in the store
        </label>
    </div>

    <div class="adm-panel">
        <h2>Images</h2>
        @if ($product->image)
            <img class="img-preview" src="{{ $product->image_url }}" alt="Current image">
        @endif
        <div class="form-grid">
            <div class="field">
                <label for="image_file">Main image — upload</label>
                <input id="image_file" type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="field">
                <label for="image">…or main image URL</label>
                <input id="image" type="url" name="image" value="{{ old('image', Str::startsWith((string) $product->image, 'http') ? $product->image : '') }}" placeholder="https://…">
            </div>
        </div>

        @if ($product->exists && $product->images->isNotEmpty())
            <label style="font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:var(--moss)">Gallery images</label>
            <div class="gallery-row">
                @foreach ($product->images as $image)
                    <div class="g-item">
                        <img src="{{ $image->url }}" alt="">
                        <form method="POST" action="{{ route('admin.products.images.destroy', $image) }}" onsubmit="return confirm('Remove this image?')">
                            @csrf @method('DELETE')
                            <button type="submit" aria-label="Remove image">×</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="field">
            <label for="gallery">Add gallery images (multiple)</label>
            <input id="gallery" type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple>
        </div>
    </div>

    <div style="display:flex;gap:12px">
        <button type="submit" class="btn-sm">{{ $product->exists ? 'Save changes' : 'Create product' }}</button>
        <a href="{{ route('admin.products.index') }}" class="btn-sm ghost">Cancel</a>
    </div>
</form>
@endsection
