@extends('layouts.admin')

@section('title', 'Order '.$order->order_number)

@section('content')
<div class="adm-toolbar">
    <a href="{{ route('admin.orders.index') }}" class="btn-sm ghost">← All orders</a>
    <div style="display:flex;gap:10px;align-items:center">
        <span class="badge b-{{ $order->payment_status }}">{{ $order->payment_status_label }}</span>
        <span class="badge b-{{ $order->status }}">{{ $order->status_label }}</span>
    </div>
</div>

<div class="adm-2col">
    <div>
        {{-- Items --}}
        <div class="adm-panel">
            <h2>Items</h2>
            <div class="adm-table-wrap" style="border:none">
                <table class="adm-table">
                    <thead><tr><th></th><th>Piece</th><th>Price</th><th>Qty</th><th style="text-align:right">Total</th></tr></thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td style="width:52px"><img class="thumb" src="{{ $item->image_url }}" alt=""></td>
                                <td>
                                    @if ($item->product)
                                        <a class="row-link" href="{{ route('admin.products.edit', $item->product) }}">{{ $item->product_name }}</a>
                                    @else
                                        {{ $item->product_name }} <span class="muted">(deleted)</span>
                                    @endif
                                    @if ($item->option_lines)
                                        <div class="opt-lines">@foreach ($item->option_lines as $opt)<span>{{ $opt }}</span>@endforeach</div>
                                    @endif
                                </td>
                                <td class="num">{{ price_format($item->price) }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td class="num" style="text-align:right">{{ price_format($item->line_total) }}</td>
                            </tr>
                        @endforeach
                        <tr><td colspan="4" class="muted">Subtotal</td><td class="num" style="text-align:right">{{ price_format($order->subtotal) }}</td></tr>
                        @if ($order->discount > 0)
                            <tr><td colspan="4" class="muted">Discount @if($order->coupon_code)({{ $order->coupon_code }})@endif</td><td class="num" style="text-align:right">−{{ price_format($order->discount) }}</td></tr>
                        @endif
                        <tr><td colspan="4" class="muted">Shipping</td><td class="num" style="text-align:right">{{ $order->shipping_fee > 0 ? price_format($order->shipping_fee) : 'Free' }}</td></tr>
                        @if ($order->tax > 0)
                            <tr><td colspan="4" class="muted">Tax</td><td class="num" style="text-align:right">{{ price_format($order->tax) }}</td></tr>
                        @endif
                        @if ($order->payment_fee > 0)
                            <tr><td colspan="4" class="muted">Cash on delivery fee</td><td class="num" style="text-align:right">{{ price_format($order->payment_fee) }}</td></tr>
                        @endif
                        <tr><td colspan="4"><b style="font-weight:500">Total</b></td><td class="num" style="text-align:right"><b style="font-weight:500">{{ price_format($order->total) }}</b></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Payment verification --}}
        <div class="adm-panel" id="payment">
            <h2>Payment verification</h2>
            <div class="adm-2col" style="gap:20px">
                <div>
                    <dl class="pd-meta" style="margin-top:0;border-top:none">
                        <div><dt>Method</dt><dd>{{ $order->payment_method_label }}</dd></div>
                        @if ($order->bankAccount)
                            <div><dt>Paid to</dt><dd>{{ $order->bankAccount->display_name }} · {{ $order->bankAccount->account_number }}<br><span class="muted">{{ $order->bankAccount->account_name }}</span></dd></div>
                        @endif
                        <div><dt>{{ $order->isCod() ? 'Collect on delivery' : 'Amount due' }}</dt><dd><b style="font-weight:500">{{ price_format($order->total) }}</b></dd></div>
                        @if ($order->payer_name)
                            <div><dt>Sent by</dt><dd>{{ $order->payer_name }}</dd></div>
                        @endif
                        @if ($order->payment_reference)
                            <div><dt>Transaction ID</dt><dd>{{ $order->payment_reference }}</dd></div>
                        @endif
                        @if ($order->payment_verified_at)
                            <div><dt>Verified at</dt><dd>{{ $order->payment_verified_at->format('M j, Y H:i') }}</dd></div>
                        @endif
                    </dl>

                    @if ($order->payment_status === 'rejected' && $order->payment_note)
                        <div class="reject-note">Rejection reason sent to customer: {{ $order->payment_note }}</div>
                    @endif

                    @if ($order->status === 'cancelled')
                        <p class="muted" style="font-size:13px">This order is cancelled. Reactivate it (set status to pending) to handle payment.</p>
                    @elseif ($order->isCod())
                        @if ($order->payment_status !== 'verified')
                            <p class="muted" style="font-size:13px;margin-top:12px">Cash is collected by the courier. It is marked collected automatically when the order is delivered, or you can mark it now.</p>
                            <form method="POST" action="{{ route('admin.orders.verify', $order) }}" style="margin:12px 0 0">
                                @csrf
                                <button type="submit" class="btn-sm success" onclick="return confirm('Mark the cash as collected?')">✓ Mark cash collected</button>
                            </form>
                        @else
                            <p class="muted" style="font-size:13px">Cash collected.</p>
                        @endif
                    @elseif ($order->payment_status !== 'verified')
                        <form method="POST" action="{{ route('admin.orders.verify', $order) }}" style="margin:16px 0 14px">
                            @csrf
                            <button type="submit" class="btn-sm success" onclick="return confirm('Confirm this payment as received?')">✓ Verify payment</button>
                        </form>
                        <form method="POST" action="{{ route('admin.orders.reject', $order) }}">
                            @csrf
                            <div class="field" style="margin-bottom:10px">
                                <label for="payment_note">Reject with reason (shown to customer)</label>
                                <textarea id="payment_note" name="payment_note" style="min-height:64px" placeholder="Amount does not match, screenshot unreadable…">{{ old('payment_note') }}</textarea>
                            </div>
                            <button type="submit" class="btn-sm danger">✕ Reject payment</button>
                        </form>
                    @else
                        <p class="muted" style="font-size:13px">Payment verified. Use the status panel to move the order forward.</p>
                    @endif
                </div>
                <div>
                    <label style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--moss);display:block;margin-bottom:10px">Customer screenshot</label>
                    @if ($order->isCod())
                        <div class="adm-empty" style="padding:30px"><span class="gem">✦</span>Cash on delivery — no receipt.</div>
                    @elseif ($order->payment_screenshot)
                        <a class="shot-box" href="{{ route('orders.screenshot', $order) }}" target="_blank" title="Open full size">
                            <img src="{{ route('orders.screenshot', $order) }}" alt="Payment screenshot">
                        </a>
                    @else
                        <div class="adm-empty" style="padding:30px"><span class="gem">✦</span>No screenshot uploaded.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div>
        {{-- Status --}}
        <div class="adm-panel">
            <h2>Order status</h2>
            <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                @csrf
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        @foreach ($order->nextStatuses() as $status)
                            @php $blocked = $order->transitionError($status); @endphp
                            <option value="{{ $status }}" @selected(old('status', $order->status) === $status) @disabled($blocked)>
                                {{ ucfirst($status) }}{{ $status === $order->status ? ' (current)' : '' }}{{ $blocked ? ' — verify payment first' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="tracking_number">Tracking number</label>
                    <input id="tracking_number" type="text" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}">
                </div>
                <div class="field">
                    <label for="admin_note">Internal note (not shown to customer)</label>
                    <textarea id="admin_note" name="admin_note" style="min-height:64px">{{ old('admin_note', $order->admin_note) }}</textarea>
                </div>
                <button type="submit" class="btn-sm">Update order</button>
            </form>
            <p class="muted" style="font-size:12px;margin-top:12px">Only the next allowed steps are listed. Cancelling restores stock and the coupon use; delivered orders are final.</p>
        </div>

        {{-- Customer --}}
        <div class="adm-panel">
            <h2>Customer</h2>
            <dl class="pd-meta" style="margin-top:0;border-top:none">
                @if ($order->user)
                    <div><dt>Account</dt><dd><a class="row-link" href="{{ route('admin.customers.show', $order->user) }}">{{ $order->user->name }}</a></dd></div>
                    <div><dt>Email</dt><dd>{{ $order->user->email }}</dd></div>
                @endif
                <div><dt>Ship to</dt><dd>{{ $order->ship_name }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $order->ship_phone }}</dd></div>
                <div><dt>Address</dt><dd>{{ $order->ship_address }}</dd></div>
                <div><dt>City</dt><dd>{{ collect([$order->ship_city, $order->ship_state, $order->ship_postal])->filter()->implode(', ') }}</dd></div>
                <div><dt>Country</dt><dd>{{ $order->ship_country }}</dd></div>
                <div><dt>Placed</dt><dd>{{ $order->created_at->format('M j, Y H:i') }}</dd></div>
                @if ($order->shipped_at)
                    <div><dt>Shipped</dt><dd>{{ $order->shipped_at->format('M j, Y H:i') }}</dd></div>
                @endif
                @if ($order->delivered_at)
                    <div><dt>Delivered</dt><dd>{{ $order->delivered_at->format('M j, Y H:i') }}</dd></div>
                @endif
            </dl>
            @if ($order->order_note)
                <p style="font-size:13.5px;color:var(--moss);margin-top:12px"><b style="font-weight:500;color:var(--forest)">Customer note:</b> {{ $order->order_note }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
