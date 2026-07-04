@extends('layouts.admin')

@section('title', 'Coupons')

@section('content')
<div class="adm-toolbar">
    <span style="color:var(--moss);font-size:13.5px">Discount codes customers can apply in the cart.</span>
    <a href="{{ route('admin.coupons.create') }}" class="btn-sm">+ New coupon</a>
</div>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th>Code</th><th>Discount</th><th>Min order</th><th>Usage</th><th>Window</th><th>Status</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($coupons as $coupon)
                <tr>
                    <td><a class="row-link num" href="{{ route('admin.coupons.edit', $coupon) }}">{{ $coupon->code }}</a></td>
                    <td>{{ $coupon->summary }}</td>
                    <td class="muted">{{ $coupon->min_order ? price_format($coupon->min_order) : '—' }}</td>
                    <td class="muted">{{ $coupon->used_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</td>
                    <td class="muted">
                        {{ $coupon->starts_at?->format('M j, Y') ?? '∞' }} → {{ $coupon->expires_at?->format('M j, Y') ?? '∞' }}
                    </td>
                    <td>
                        @php $expired = $coupon->expires_at && $coupon->expires_at->isPast(); @endphp
                        <span class="badge {{ $coupon->is_active && ! $expired ? 'b-verified' : 'b-cancelled' }}">
                            {{ $expired ? 'Expired' : ($coupon->is_active ? 'Active' : 'Off') }}
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn-sm ghost">Edit</a>
                            <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" onsubmit="return confirm('Delete coupon {{ $coupon->code }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="adm-empty"><span class="gem">✦</span>No coupons yet.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px">{{ $coupons->links('partials.pager') }}</div>
@endsection
