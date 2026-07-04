@extends('layouts.app')

@section('title', $order->order_number.' — '.setting('store_name', 'Eluxee'))

@section('content')
<div class="container account-wrap">
    <div class="account-layout">
        @include('account.partials.side-nav')

        <div>
            <div class="account-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap">
                <div>
                    <h1 class="display" style="font-size:clamp(24px,3vw,32px)">Order {{ $order->order_number }}</h1>
                    <p>Placed {{ $order->created_at->format('F j, Y \a\t H:i') }}</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <span class="badge b-{{ $order->status }}">{{ $order->status_label }}</span>
                    <span class="badge b-{{ $order->payment_status }}">{{ $order->payment_status_label }}</span>
                </div>
            </div>

            @if ($order->status !== 'cancelled')
                @php
                    $steps = ['pending' => 'Placed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
                    $reached = array_search($order->status, array_keys($steps), true);
                @endphp
                <div class="timeline">
                    @foreach ($steps as $key => $label)
                        <div @class(['done' => $reached !== false && $loop->index <= $reached])>{{ $label }}</div>
                    @endforeach
                </div>
            @endif

            @if ($order->payment_status === 'rejected')
                <div class="reject-note">
                    <b style="font-weight:500">Your payment could not be verified.</b>
                    @if ($order->payment_note) Reason: {{ $order->payment_note }}@endif
                    <br>Please upload a new screenshot below.
                </div>
            @endif

            <div class="order-cols">
                <div>
                    <div class="panel">
                        <h3>Items</h3>
                        <div class="co-items">
                            @foreach ($order->items as $item)
                                <div class="co-item">
                                    <img src="{{ $item->image_url }}" alt="{{ $item->product_name }}">
                                    <div>
                                        <div class="ci-name">
                                            @if ($item->product)
                                                <a href="{{ route('product.show', $item->product) }}" style="border-bottom:1px solid var(--line)">{{ $item->product_name }}</a>
                                            @else
                                                {{ $item->product_name }}
                                            @endif
                                        </div>
                                        <div class="ci-qty">Qty {{ $item->quantity }} · {{ price_format($item->price) }} each</div>
                                    </div>
                                    <span class="ci-price">{{ price_format($item->line_total) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="sum-row"><span>Subtotal</span><b>{{ price_format($order->subtotal) }}</b></div>
                        @if ($order->discount > 0)
                            <div class="sum-row"><span>Discount @if($order->coupon_code)({{ $order->coupon_code }})@endif</span><b>−{{ price_format($order->discount) }}</b></div>
                        @endif
                        <div class="sum-row"><span>Shipping</span><b>{{ $order->shipping_fee > 0 ? price_format($order->shipping_fee) : 'Free' }}</b></div>
                        @if ($order->tax > 0)
                            <div class="sum-row"><span>Tax</span><b>{{ price_format($order->tax) }}</b></div>
                        @endif
                        <div class="sum-row total"><span>Total</span><span>{{ price_format($order->total) }}</span></div>
                    </div>

                    @if ($order->canResubmitPayment())
                        <div class="panel">
                            <h3>Resubmit payment proof</h3>
                            <form method="POST" action="{{ route('account.orders.payment', $order) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="upload-box" id="uploadBox">
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M12 16V4m0 0 4 4m-4-4L8 8"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                                    <div class="up-title">Click to upload a new screenshot</div>
                                    <div class="up-hint">JPG, PNG or WEBP · max 5 MB</div>
                                    <img id="uploadPreview" class="upload-preview" alt="Payment screenshot preview">
                                    <input type="file" name="payment_screenshot" accept="image/jpeg,image/png,image/webp">
                                </div>
                                @error('payment_screenshot')<span class="field-error" style="display:block;margin-top:10px">{{ $message }}</span>@enderror
                                <div class="field" style="margin-top:18px">
                                    <label for="payment_reference">Transaction reference (optional)</label>
                                    <input id="payment_reference" type="text" name="payment_reference" value="{{ old('payment_reference', $order->payment_reference) }}">
                                </div>
                                <button type="submit" class="btn btn-solid">Submit new screenshot</button>
                            </form>
                        </div>
                    @endif
                </div>

                <div>
                    <div class="panel">
                        <h3>Payment</h3>
                        <dl class="pd-meta" style="margin-top:0">
                            <div><dt>Method</dt><dd>Bank transfer</dd></div>
                            @if ($order->bankAccount)
                                <div><dt>Bank</dt><dd>{{ $order->bankAccount->bank_name }}</dd></div>
                                <div><dt>Account</dt><dd>{{ $order->bankAccount->account_number }}</dd></div>
                            @endif
                            @if ($order->payment_reference)
                                <div><dt>Reference</dt><dd>{{ $order->payment_reference }}</dd></div>
                            @endif
                            @if ($order->payment_verified_at)
                                <div><dt>Verified</dt><dd>{{ $order->payment_verified_at->format('M j, Y H:i') }}</dd></div>
                            @endif
                        </dl>
                        @if ($order->payment_screenshot)
                            <p style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--moss);margin:16px 0 10px">Your screenshot</p>
                            <a href="{{ route('orders.screenshot', $order) }}" target="_blank">
                                <img class="screenshot-thumb" src="{{ route('orders.screenshot', $order) }}" alt="Payment screenshot">
                            </a>
                        @endif
                    </div>

                    <div class="panel">
                        <h3>Shipping</h3>
                        <dl class="pd-meta" style="margin-top:0">
                            <div><dt>Name</dt><dd>{{ $order->ship_name }}</dd></div>
                            <div><dt>Phone</dt><dd>{{ $order->ship_phone }}</dd></div>
                            <div><dt>Address</dt><dd>{{ $order->ship_address }}</dd></div>
                            <div><dt>City</dt><dd>{{ collect([$order->ship_city, $order->ship_state, $order->ship_postal])->filter()->implode(', ') }}</dd></div>
                            <div><dt>Country</dt><dd>{{ $order->ship_country }}</dd></div>
                            @if ($order->tracking_number)
                                <div><dt>Tracking</dt><dd>{{ $order->tracking_number }}</dd></div>
                            @endif
                        </dl>
                        @if ($order->order_note)
                            <p style="font-size:14px;color:var(--moss);margin-top:14px"><b style="font-weight:400;color:var(--forest)">Note:</b> {{ $order->order_note }}</p>
                        @endif
                    </div>

                    @if ($order->isCancellable())
                        <form method="POST" action="{{ route('account.orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order? This cannot be undone.')">
                            @csrf
                            <button type="submit" class="btn btn-ghost" style="width:100%;justify-content:center;border-color:#B05B5B;color:#B05B5B">Cancel order</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
