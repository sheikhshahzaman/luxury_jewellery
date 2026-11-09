@extends('layouts.admin')

@section('title', 'Products')

@section('content')
<div class="adm-toolbar">
    <form method="GET" action="{{ route('admin.products.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, SKU…">
        <select name="category" onchange="this.form.submit()">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()">
            <option value="">Any status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <label class="switch-row" style="padding:0">
            <input type="checkbox" name="stock" value="low" @checked(request('stock') === 'low') onchange="this.form.submit()"> Low stock
        </label>
        <button type="submit" class="btn-sm ghost">Filter</button>
    </form>
    <a href="{{ route('admin.products.create') }}" class="btn-sm">+ New product</a>
</div>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th></th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Flags</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td style="width:56px"><img class="thumb" src="{{ $product->image_url }}" alt=""></td>
                    <td>
                        <a class="row-link" href="{{ route('admin.products.edit', $product) }}">{{ $product->name }}</a>
                        <div class="muted">{{ $product->sku ?: '—' }}</div>
                    </td>
                    <td class="muted">{{ $product->category?->name ?? '—' }}</td>
                    <td class="num">
                        {{ price_format($product->current_price) }}
                        @if ($product->has_discount)
                            <div class="muted" style="text-decoration:line-through">{{ price_format($product->price) }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $product->stock === 0 ? 'b-rejected' : ($product->stock <= 5 ? 'b-pending' : 'b-verified') }}">{{ $product->stock }}</span>
                    </td>
                    <td class="muted">
                        {{ $product->is_active ? 'Active' : 'Hidden' }}@if($product->is_featured) · Featured @endif @if($product->tag) · {{ $product->tag }} @endif
                    </td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('product.show', $product) }}" target="_blank" class="btn-sm ghost">View</a>
                            <a href="{{ route('admin.products.edit', $product) }}" class="btn-sm ghost">Edit</a>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm({{ Js::from('Delete “'.$product->name.'”? Existing orders keep their line items.') }})">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="adm-empty"><span class="gem">✦</span>No products found.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px">{{ $products->links('partials.pager') }}</div>
@endsection
