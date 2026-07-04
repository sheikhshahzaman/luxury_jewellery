@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
<div class="adm-toolbar">
    <span class="muted" style="color:var(--moss);font-size:13.5px">{{ $categories->count() }} categories</span>
    <a href="{{ route('admin.categories.create') }}" class="btn-sm">+ New category</a>
</div>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th></th><th>Name</th><th>Slug</th><th>Products</th><th>Status</th><th>Order</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td style="width:56px"><img class="thumb" src="{{ $category->image_url }}" alt=""></td>
                    <td><a class="row-link" href="{{ route('admin.categories.edit', $category) }}">{{ $category->name }}</a></td>
                    <td class="muted">{{ $category->slug }}</td>
                    <td>{{ $category->products_count }}</td>
                    <td><span class="badge {{ $category->is_active ? 'b-verified' : 'b-cancelled' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span></td>
                    <td class="muted">{{ $category->sort_order }}</td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn-sm ghost">Edit</a>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete “{{ $category->name }}”?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="adm-empty"><span class="gem">✦</span>No categories yet.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
