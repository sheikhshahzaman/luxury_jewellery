@extends('layouts.app')

@section('title', setting('offers_title', 'Offers & Coupons').' — '.setting('store_name', 'Eluxee'))

@section('content')
<div class="page-strip">
    <div class="container">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a><span class="sep">✦</span>Offers</div>
        <h1 class="display">{{ setting('offers_title', 'Offers & coupons') }}</h1>
    </div>
</div>

<div class="container" style="padding:56px 0 96px">
    <p style="color:var(--moss);max-width:56ch;margin-bottom:40px">{{ setting('offers_intro', 'Copy a code below and paste it in your cart to save on your next order. Codes are limited — use them while they last.') }}</p>

    @if ($coupons->isEmpty())
        <div class="empty">
            <span class="gem">✦</span>
            <h3>No active offers right now</h3>
            <p>Join our newsletter and we will let you know the moment a new code goes live.</p>
            <a href="{{ route('shop.index') }}" class="btn btn-solid">Shop the collection</a>
        </div>
    @else
        <div class="offers-grid">
            @foreach ($coupons as $coupon)
                <div class="offer-card reveal">
                    <div class="offer-badge">{{ $coupon->summary }}</div>
                    <div class="offer-body">
                        <div class="offer-terms">
                            @if ($coupon->min_order)
                                <span>On orders over {{ price_format($coupon->min_order) }}</span>
                            @else
                                <span>On any order</span>
                            @endif
                            @if ($coupon->expires_at)
                                <span>Ends {{ $coupon->expires_at->format('M j, Y') }}</span>
                            @endif
                        </div>
                        <div class="offer-code">
                            <code>{{ $coupon->code }}</code>
                            <button type="button" class="offer-copy" data-code="{{ $coupon->code }}">Copy</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="form-note" style="margin-top:34px">Have a code already? <a href="{{ route('cart.index') }}">Go to your cart</a> to apply it at checkout.</p>
    @endif
</div>
@endsection
