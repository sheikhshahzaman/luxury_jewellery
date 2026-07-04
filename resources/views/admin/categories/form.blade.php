@extends('layouts.admin')

@section('title', $category->exists ? 'Edit category' : 'New category')

@section('content')
<form class="adm-form" method="POST"
      action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if ($category->exists) @method('PUT') @endif

    <div class="adm-panel">
        <h2>Category</h2>
        <div class="form-grid">
            <div class="field">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $category->name) }}" required>
            </div>
            <div class="field">
                <label for="sort_order">Sort order</label>
                <input id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
            </div>
            <div class="field full">
                <label for="description">Description (optional)</label>
                <textarea id="description" name="description">{{ old('description', $category->description) }}</textarea>
            </div>
        </div>
        <label class="switch-row">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->exists ? $category->is_active : true))>
            Active — visible in the store
        </label>
    </div>

    <div class="adm-panel">
        <h2>Image</h2>
        @if ($category->image)
            <img class="img-preview" src="{{ $category->image_url }}" alt="Current image">
        @endif
        <div class="form-grid">
            <div class="field">
                <label for="image_file">Upload image</label>
                <input id="image_file" type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="field">
                <label for="image">…or image URL</label>
                <input id="image" type="url" name="image" value="{{ old('image', Str::startsWith((string) $category->image, 'http') ? $category->image : '') }}" placeholder="https://…">
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px">
        <button type="submit" class="btn-sm">{{ $category->exists ? 'Save changes' : 'Create category' }}</button>
        <a href="{{ route('admin.categories.index') }}" class="btn-sm ghost">Cancel</a>
    </div>
</form>
@endsection
