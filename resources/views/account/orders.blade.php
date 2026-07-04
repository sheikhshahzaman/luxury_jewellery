@extends('layouts.app')

@section('title', 'My orders — '.setting('store_name', 'Eluxee'))

@section('content')
<div class="container account-wrap">
    <div class="account-layout">
        @include('account.partials.side-nav')

        <div>
            <div class="account-head">
                <h1 class="display">My orders</h1>
                <p>Every order and its payment status, newest first.</p>
            </div>

            @if ($orders->isEmpty())
                <div class="empty">
                    <span class="gem">✦</span>
                    <h3>No orders yet</h3>
                    <p>Your orders will appear here once you make your first purchase.</p>
                    <a href="{{ route('shop.index') }}" class="btn btn-solid">Start shopping</a>
                </div>
            @else
                <div class="table-wrap">
                    <table class="shop-table">
                        <thead>
                            <tr><th>Order</th><th>Date</th><th>Items</th><th>Status</th><th>Payment</th><th style="text-align:right">Total</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="num">{{ $order->order_number }}</td>
                                    <td>{{ $order->created_at->format('M j, Y') }}</td>
                                    <td>{{ $order->items->sum('quantity') ?: $order->items()->sum('quantity') }}</td>
                                    <td><span class="badge b-{{ $order->status }}">{{ $order->status_label }}</span></td>
                                    <td><span class="badge b-{{ $order->payment_status }}">{{ $order->payment_status_label }}</span></td>
                                    <td class="num" style="text-align:right">{{ price_format($order->total) }}</td>
                                    <td style="text-align:right"><a class="link-quiet" href="{{ route('account.orders.show', $order) }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $orders->links('partials.pager') }}
            @endif
        </div>
    </div>
</div>
@endsection
