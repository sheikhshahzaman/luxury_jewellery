@extends('layouts.admin')

@section('title', $customer->name)

@section('content')
<div class="adm-toolbar">
    <a href="{{ route('admin.customers.index') }}" class="btn-sm ghost">← All customers</a>
    <form method="POST" action="{{ route('admin.customers.toggle-block', $customer) }}">
        @csrf
        <button type="submit" class="btn-sm {{ $customer->is_blocked ? 'success' : 'danger' }}">{{ $customer->is_blocked ? 'Unblock customer' : 'Block customer' }}</button>
    </form>
</div>

<div class="adm-cards" style="grid-template-columns:repeat(3,1fr)">
    <div class="adm-card">
        <div class="label">Orders</div>
        <div class="value">{{ $customer->orders()->count() }}</div>
    </div>
    <div class="adm-card">
        <div class="label">Verified spend</div>
        <div class="value">{{ price_format($totalSpent) }}</div>
    </div>
    <div class="adm-card">
        <div class="label">Member since</div>
        <div class="value" style="font-size:22px">{{ $customer->created_at->format('M Y') }}</div>
        <div class="sub">{{ $customer->email }} @if($customer->phone) · {{ $customer->phone }} @endif</div>
    </div>
</div>

<div class="adm-panel">
    <h2>Orders</h2>
    @if ($orders->isEmpty())
        <div class="adm-empty"><span class="gem">✦</span>No orders yet.</div>
    @else
        <div class="adm-table-wrap" style="border:none">
            <table class="adm-table">
                <thead><tr><th>Order</th><th>Date</th><th>Payment</th><th>Status</th><th style="text-align:right">Total</th></tr></thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td><a class="row-link num" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                            <td class="muted">{{ $order->created_at->format('M j, Y') }}</td>
                            <td><span class="badge b-{{ $order->payment_status }}">{{ ucfirst($order->payment_status) }}</span></td>
                            <td><span class="badge b-{{ $order->status }}">{{ $order->status_label }}</span></td>
                            <td class="num" style="text-align:right">{{ price_format($order->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px">{{ $orders->links('partials.pager') }}</div>
    @endif
</div>

@if ($customer->addresses->isNotEmpty())
    <div class="adm-panel">
        <h2>Saved addresses</h2>
        @foreach ($customer->addresses as $address)
            <p style="font-size:14px;color:var(--moss);padding:8px 0;border-bottom:1px solid var(--line)">
                <b style="font-weight:500;color:var(--forest)">{{ $address->label ?: 'Address' }}:</b>
                {{ $address->name }} · {{ $address->phone }} · {{ $address->one_line }}
                @if ($address->is_default) <span class="badge b-verified">Default</span> @endif
            </p>
        @endforeach
    </div>
@endif
@endsection
