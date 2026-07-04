@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
<div class="adm-toolbar">
    <form method="GET" action="{{ route('admin.orders.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Order #, customer…">
        <select name="status" onchange="this.form.submit()">
            <option value="">Any status</option>
            @foreach (\App\Models\Order::STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <select name="payment" onchange="this.form.submit()">
            <option value="">Any payment</option>
            <option value="submitted" @selected(request('payment') === 'submitted')>Awaiting verification</option>
            <option value="verified" @selected(request('payment') === 'verified')>Verified</option>
            <option value="rejected" @selected(request('payment') === 'rejected')>Rejected</option>
        </select>
        <button type="submit" class="btn-sm ghost">Filter</button>
    </form>
    <a href="{{ route('admin.orders.index', ['payment' => 'submitted']) }}" class="btn-sm">Payments to verify</a>
</div>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th>Order</th><th>Customer</th><th>Date</th><th>Payment</th><th>Status</th><th style="text-align:right">Total</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td><a class="row-link num" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                    <td>
                        {{ $order->user?->name ?? $order->ship_name }}
                        <div class="muted">{{ $order->user?->email }}</div>
                    </td>
                    <td class="muted">{{ $order->created_at->format('M j, Y H:i') }}</td>
                    <td><span class="badge b-{{ $order->payment_status }}">{{ $order->payment_status_label }}</span></td>
                    <td><span class="badge b-{{ $order->status }}">{{ $order->status_label }}</span></td>
                    <td class="num" style="text-align:right">{{ price_format($order->total) }}</td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('admin.orders.show', $order) }}" class="btn-sm ghost">Manage</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="adm-empty"><span class="gem">✦</span>No orders match these filters.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px">{{ $orders->links('partials.pager') }}</div>
@endsection
