@extends('layouts.app')

@section('title', 'My account — '.setting('store_name', 'Lax Jewellers'))

@section('content')
<div class="container account-wrap">
    <div class="account-layout">
        @include('account.partials.side-nav')

        <div>
            <div class="account-head">
                <h1 class="display">Hello, {{ Str::before($user->name, ' ') }} ✦</h1>
                <p>From here you can follow orders, manage your wishlist and update your details.</p>
            </div>

            <div class="stat-cards">
                <div class="stat-card">
                    <div class="sc-num">{{ $ordersCount }}</div>
                    <div class="sc-label">Total orders</div>
                </div>
                <div class="stat-card">
                    <div class="sc-num">{{ $pendingCount }}</div>
                    <div class="sc-label">In progress</div>
                </div>
                <div class="stat-card">
                    <div class="sc-num">{{ $wishlistCount }}</div>
                    <div class="sc-label">Wishlist pieces</div>
                </div>
            </div>

            <div class="section-head" style="margin-bottom:22px">
                <h2 class="display" style="font-size:24px">Recent orders</h2>
                <a href="{{ route('account.orders') }}" class="link-more">All orders</a>
            </div>

            @if ($recentOrders->isEmpty())
                <div class="empty">
                    <span class="gem">✦</span>
                    <h3>No orders yet</h3>
                    <p>Your orders will appear here once you make your first purchase.</p>
                    <a href="{{ route('shop.index') }}" class="btn btn-solid">Start shopping</a>
                </div>
            @else
                <div class="table-wrap">
                    <table class="shop-table stack">
                        <thead>
                            <tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th style="text-align:right">Total</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($recentOrders as $order)
                                <tr>
                                    <td data-label="Order" class="num">{{ $order->order_number }}</td>
                                    <td data-label="Date">{{ $order->created_at->format('M j, Y') }}</td>
                                    <td data-label="Status"><span class="badge b-{{ $order->status }}">{{ $order->status_label }}</span></td>
                                    <td data-label="Payment"><span class="badge b-{{ $order->payment_status }}">{{ $order->payment_status_label }}</span></td>
                                    <td data-label="Total" class="num" style="text-align:right">{{ price_format($order->total) }}</td>
                                    <td class="row-action" style="text-align:right"><a class="link-quiet" href="{{ route('account.orders.show', $order) }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
