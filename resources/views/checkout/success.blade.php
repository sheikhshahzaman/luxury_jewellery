@extends('layouts.app')

@section('title', 'Order received — '.setting('store_name', 'Lax Jewellers'))

@section('content')
<div class="container" style="padding:80px 24px 96px;max-width:820px">
    <div class="co-section" style="text-align:center">
        <div class="eyebrow" style="justify-content:center">Thank you</div>
        <h1 class="display" style="font-size:clamp(30px,4vw,44px);margin:18px 0 10px">Your order is in</h1>
        <p style="color:var(--moss);max-width:52ch;margin:0 auto 12px">
            Order <b style="font-weight:400;color:var(--forest)">{{ $order->order_number }}</b>
            @if ($order->isCod())
                is confirmed. Please keep <b style="font-weight:400;color:var(--forest)">{{ price_format($order->total) }}</b> in cash ready for the courier — we will call you before dispatch.
            @else
                has been received with your payment receipt. It stays pending while our team verifies the payment, usually within a few hours.
            @endif
            You can follow every step in your account.
        </p>
        <span class="badge b-{{ $order->payment_status }}" style="margin:10px 0 30px">{{ $order->payment_status_label }}</span>

        <div class="table-wrap" style="text-align:left">
            <table class="shop-table">
                <thead>
                    <tr><th>Piece</th><th>Qty</th><th style="text-align:right">Total</th></tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->product_name }}
                                @if ($item->option_lines)
                                    <div class="opt-lines">@foreach ($item->option_lines as $opt)<span>{{ $opt }}</span>@endforeach</div>
                                @endif
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td style="text-align:right" class="num">{{ price_format($item->line_total) }}</td>
                        </tr>
                    @endforeach
                    @if ($order->discount > 0)
                        <tr><td colspan="2" style="color:var(--moss)">Discount @if($order->coupon_code)({{ $order->coupon_code }})@endif</td><td style="text-align:right" class="num">−{{ price_format($order->discount) }}</td></tr>
                    @endif
                    <tr><td colspan="2" style="color:var(--moss)">Shipping</td><td style="text-align:right" class="num">{{ $order->shipping_fee > 0 ? price_format($order->shipping_fee) : 'Free' }}</td></tr>
                    @if ($order->tax > 0)
                        <tr><td colspan="2" style="color:var(--moss)">Tax</td><td style="text-align:right" class="num">{{ price_format($order->tax) }}</td></tr>
                    @endif
                    @if ($order->payment_fee > 0)
                        <tr><td colspan="2" style="color:var(--moss)">Cash on delivery fee</td><td style="text-align:right" class="num">{{ price_format($order->payment_fee) }}</td></tr>
                    @endif
                    <tr><td colspan="2"><b style="font-weight:400">Total</b></td><td style="text-align:right" class="num"><b style="font-weight:400">{{ price_format($order->total) }}</b></td></tr>
                </tbody>
            </table>
        </div>

        <p style="color:var(--moss);font-size:14px;margin-top:22px">
            Payment: <b style="font-weight:400;color:var(--forest)">{{ $order->payment_method_label }}</b>
            @if ($order->bankAccount)
                · {{ $order->bankAccount->display_name }} {{ $order->bankAccount->account_number }}
            @endif
            @if ($order->payment_reference)
                · Transaction ID {{ $order->payment_reference }}
            @endif
        </p>

        <div style="display:flex;gap:14px;justify-content:center;margin-top:30px;flex-wrap:wrap">
            <a href="{{ route('account.orders.show', $order) }}" class="btn btn-solid">Track this order</a>
            <a href="{{ route('shop.index') }}" class="btn btn-ghost">Continue shopping</a>
        </div>
    </div>
</div>
@endsection
