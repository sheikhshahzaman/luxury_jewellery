@extends('layouts.app')

@section('title', 'Your cart — '.setting('store_name', 'Eluxee'))

@section('content')
<div class="page-strip">
    <div class="container">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a><span class="sep">✦</span>Cart</div>
        <h1 class="display">Your cart</h1>
    </div>
</div>

<div class="container cart-wrap">
    @if ($items->isEmpty())
        <div class="empty">
            <span class="gem">✦</span>
            <h3>Your cart is empty</h3>
            <p>Discover handcrafted pieces made to be kept for decades.</p>
            <a href="{{ route('shop.index') }}" class="btn btn-solid">Shop the collection</a>
        </div>
    @else
        <div class="cart-grid">
            <div class="cart-lines">
                @foreach ($items as $line)
                    @php $product = $line['product']; @endphp
                    <div class="cart-line">
                        <a href="{{ route('product.show', $product) }}"><img src="{{ $product->image_url }}" alt="{{ $product->name }}"></a>
                        <div>
                            <div class="cl-name"><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></div>
                            @if ($product->category)
                                <div class="cl-cat">{{ $product->category->name }}</div>
                            @endif
                            <div class="cl-price">{{ price_format($product->current_price) }} each</div>
                            <form method="POST" action="{{ route('cart.update', $product) }}" style="margin-top:12px">
                                @csrf
                                <div class="qty-box" data-autosubmit="1">
                                    <button type="button" data-step="-1" aria-label="Decrease">−</button>
                                    <input type="number" name="qty" value="{{ $line['qty'] }}" min="1" max="{{ $product->stock }}" onchange="this.form.submit()" aria-label="Quantity">
                                    <button type="button" data-step="1" aria-label="Increase">+</button>
                                </div>
                            </form>
                        </div>
                        <div class="cl-right">
                            <span class="cl-total">{{ price_format($line['line_total']) }}</span>
                            <form method="POST" action="{{ route('cart.remove', $product) }}">
                                @csrf
                                <button type="submit" class="cl-remove">Remove</button>
                            </form>
                        </div>
                    </div>
                @endforeach
                <div style="margin-top:26px">
                    <a href="{{ route('shop.index') }}" class="link-quiet">← Continue shopping</a>
                </div>
            </div>

            <aside class="summary-card">
                <h3>Order summary</h3>
                <div class="sum-row"><span>Subtotal</span><b>{{ price_format($subtotal) }}</b></div>
                @if ($discount > 0)
                    <div class="sum-row"><span>Discount ({{ $coupon->code }})</span><b>−{{ price_format($discount) }}</b></div>
                @endif
                <div class="sum-row"><span>Shipping</span>@if ($shipping == 0)<span class="free">Free</span>@else<b>{{ price_format($shipping) }}</b>@endif</div>
                @if ($tax > 0)
                    <div class="sum-row"><span>Tax</span><b>{{ price_format($tax) }}</b></div>
                @endif
                <div class="sum-row total"><span>Total</span><span>{{ price_format($total) }}</span></div>

                @if ($coupon)
                    <div class="coupon-on">
                        <span>✦ {{ $coupon->code }} — {{ $coupon->summary }}</span>
                        <form method="POST" action="{{ route('coupon.remove') }}">
                            @csrf
                            <button type="submit" aria-label="Remove coupon">×</button>
                        </form>
                    </div>
                @else
                    <form class="coupon-form" method="POST" action="{{ route('coupon.apply') }}">
                        @csrf
                        <input type="text" name="code" placeholder="Coupon code" required>
                        <button type="submit">Apply</button>
                    </form>
                @endif

                <a href="{{ route('checkout.show') }}" class="btn btn-solid">Proceed to checkout
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
                @php $freeMin = (float) setting('free_shipping_min', 150); @endphp
                @if ($shipping > 0 && $freeMin > 0)
                    <div class="free-ship-note">Add {{ price_format($freeMin - $subtotal) }} more for free shipping</div>
                @endif
                <div class="free-ship-note">Payment by direct bank transfer</div>
            </aside>
        </div>
    @endif
</div>
@endsection
