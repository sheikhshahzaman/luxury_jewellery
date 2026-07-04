@extends('layouts.admin')

@section('title', $coupon->exists ? 'Edit coupon' : 'New coupon')

@section('content')
<form class="adm-form" method="POST"
      action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}">
    @csrf
    @if ($coupon->exists) @method('PUT') @endif

    <div class="adm-panel">
        <h2>Coupon</h2>
        <div class="form-grid">
            <div class="field">
                <label for="code">Code</label>
                <input id="code" type="text" name="code" value="{{ old('code', $coupon->code) }}" required placeholder="WELCOME10" style="text-transform:uppercase">
            </div>
            <div class="field">
                <label for="type">Type</label>
                <select id="type" name="type">
                    <option value="percent" @selected(old('type', $coupon->type) === 'percent')>Percentage off</option>
                    <option value="fixed" @selected(old('type', $coupon->type) === 'fixed')>Fixed amount off</option>
                </select>
            </div>
            <div class="field">
                <label for="value">Value (percent or amount)</label>
                <input id="value" type="number" step="0.01" min="0.01" name="value" value="{{ old('value', $coupon->value) }}" required>
            </div>
            <div class="field">
                <label for="min_order">Minimum order (optional)</label>
                <input id="min_order" type="number" step="0.01" min="0" name="min_order" value="{{ old('min_order', $coupon->min_order) }}">
            </div>
            <div class="field">
                <label for="max_uses">Max uses (optional)</label>
                <input id="max_uses" type="number" min="1" name="max_uses" value="{{ old('max_uses', $coupon->max_uses) }}">
            </div>
            <div class="field">
                <label for="starts_at">Starts (optional)</label>
                <input id="starts_at" type="date" name="starts_at" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d')) }}">
            </div>
            <div class="field">
                <label for="expires_at">Expires (optional)</label>
                <input id="expires_at" type="date" name="expires_at" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d')) }}">
            </div>
        </div>
        <label class="switch-row">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->exists ? $coupon->is_active : true))>
            Active — customers can apply this code
        </label>
        <label class="switch-row">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $coupon->exists ? $coupon->is_public : true))>
            Show on the public Offers page (uncheck to keep it private)
        </label>
    </div>

    <div style="display:flex;gap:12px">
        <button type="submit" class="btn-sm">{{ $coupon->exists ? 'Save changes' : 'Create coupon' }}</button>
        <a href="{{ route('admin.coupons.index') }}" class="btn-sm ghost">Cancel</a>
    </div>
</form>
@endsection
