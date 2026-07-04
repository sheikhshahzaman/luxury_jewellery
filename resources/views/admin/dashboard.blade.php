@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="adm-cards">
    <div class="adm-card accent">
        <div class="label">Verified revenue</div>
        <div class="value">{{ price_format($revenue) }}</div>
        <div class="sub">All time, verified payments</div>
    </div>
    <div class="adm-card @if($pendingPayments > 0) warn @endif">
        <div class="label">Payments to verify</div>
        <div class="value">{{ $pendingPayments }}</div>
        <div class="sub"><a href="{{ route('admin.orders.index', ['payment' => 'submitted']) }}" style="border-bottom:1px solid var(--line-dark)">Review now →</a></div>
    </div>
    <div class="adm-card">
        <div class="label">Orders</div>
        <div class="value">{{ $ordersTotal }}</div>
        <div class="sub">{{ $statusCounts->get('pending', 0) }} pending · {{ $statusCounts->get('processing', 0) }} processing</div>
    </div>
    <div class="adm-card">
        <div class="label">Customers</div>
        <div class="value">{{ $customersCount }}</div>
        <div class="sub">{{ $pendingReviews }} reviews · {{ $unreadMessages }} messages waiting</div>
    </div>
</div>

<div class="adm-2col">
    <div class="adm-panel">
        <h2>Sales — last 6 months <span style="font-size:12px;color:var(--moss);font-family:var(--body)">verified payments</span></h2>
        <div class="chart-bars">
            @foreach ($monthly as $month)
                <div class="bar">
                    <span class="m-val">{{ $month['total'] > 0 ? price_format($month['total']) : '—' }}</span>
                    <div class="fill" style="height:{{ max(2, (int) round($month['total'] / $maxMonthly * 100)) }}%"></div>
                    <span class="m-label">{{ $month['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="adm-panel">
        <h2>Low stock <a href="{{ route('admin.products.index', ['stock' => 'low']) }}">All</a></h2>
        @if ($lowStock->isEmpty())
            <div class="adm-empty"><span class="gem">✦</span>All pieces are well stocked.</div>
        @else
            <div class="adm-table-wrap" style="border:none">
                <table class="adm-table" style="min-width:0">
                    <tbody>
                        @foreach ($lowStock as $product)
                            <tr>
                                <td style="width:56px"><img class="thumb" src="{{ $product->image_url }}" alt=""></td>
                                <td><a class="row-link" href="{{ route('admin.products.edit', $product) }}">{{ $product->name }}</a></td>
                                <td style="text-align:right"><span class="badge {{ $product->stock === 0 ? 'b-rejected' : 'b-pending' }}">{{ $product->stock }} left</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="adm-2col">
    <div class="adm-panel">
        <h2>Recent orders <a href="{{ route('admin.orders.index') }}">All orders</a></h2>
        @if ($recentOrders->isEmpty())
            <div class="adm-empty"><span class="gem">✦</span>No orders yet.</div>
        @else
            <div class="adm-table-wrap" style="border:none">
                <table class="adm-table">
                    <thead>
                        <tr><th>Order</th><th>Customer</th><th>Payment</th><th>Status</th><th style="text-align:right">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($recentOrders as $order)
                            <tr>
                                <td><a class="row-link num" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                                <td class="muted">{{ $order->user?->name }}</td>
                                <td><span class="badge b-{{ $order->payment_status }}">{{ ucfirst($order->payment_status) }}</span></td>
                                <td><span class="badge b-{{ $order->status }}">{{ $order->status_label }}</span></td>
                                <td class="num" style="text-align:right">{{ price_format($order->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="adm-panel">
        <h2>Top sellers</h2>
        @if ($topProducts->isEmpty())
            <div class="adm-empty"><span class="gem">✦</span>No sales data yet.</div>
        @else
            <div class="adm-table-wrap" style="border:none">
                <table class="adm-table" style="min-width:0">
                    <thead>
                        <tr><th>Piece</th><th style="text-align:right">Sold</th><th style="text-align:right">Revenue</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($topProducts as $top)
                            <tr>
                                <td>{{ $top->product_name }}</td>
                                <td style="text-align:right">{{ $top->sold }}</td>
                                <td class="num" style="text-align:right">{{ price_format($top->revenue) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
