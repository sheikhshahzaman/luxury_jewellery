@extends('layouts.admin')

@section('title', $testimonial->exists ? 'Edit testimonial' : 'New testimonial')

@section('content')
<form class="adm-form" method="POST"
      action="{{ $testimonial->exists ? route('admin.testimonials.update', $testimonial) : route('admin.testimonials.store') }}">
    @csrf
    @if ($testimonial->exists) @method('PUT') @endif

    <div class="adm-panel">
        <h2>Testimonial</h2>
        <div class="form-grid">
            <div class="field">
                <label for="name">Customer name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $testimonial->name) }}" required>
            </div>
            <div class="field">
                <label for="location">Location (optional)</label>
                <input id="location" type="text" name="location" value="{{ old('location', $testimonial->location) }}" placeholder="Dubai">
            </div>
            <div class="field full">
                <label for="quote">Quote</label>
                <textarea id="quote" name="quote" required>{{ old('quote', $testimonial->quote) }}</textarea>
            </div>
            <div class="field">
                <label for="rating">Rating</label>
                <select id="rating" name="rating">
                    @for ($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" @selected(old('rating', $testimonial->rating ?? 5) == $i)>{{ str_repeat('★', $i) }}</option>
                    @endfor
                </select>
            </div>
            <div class="field">
                <label for="sort_order">Sort order</label>
                <input id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $testimonial->sort_order ?? 0) }}">
            </div>
        </div>
        <label class="switch-row">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $testimonial->exists ? $testimonial->is_active : true))>
            Show on home page
        </label>
    </div>

    <div style="display:flex;gap:12px">
        <button type="submit" class="btn-sm">{{ $testimonial->exists ? 'Save changes' : 'Add testimonial' }}</button>
        <a href="{{ route('admin.testimonials.index') }}" class="btn-sm ghost">Cancel</a>
    </div>
</form>
@endsection
