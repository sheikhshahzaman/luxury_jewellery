@extends('layouts.app')

@section('title', $product->name.' — '.setting('store_name', 'Eluxee'))
@section('meta_description', Str::limit(strip_tags((string) $product->description), 150))

@section('content')
<div class="container pd">
    <div class="crumbs" style="margin-bottom:36px">
        <a href="{{ route('home') }}">Home</a><span class="sep">✦</span>
        <a href="{{ route('shop.index') }}">Shop</a><span class="sep">✦</span>
        @if ($product->category)
            <a href="{{ route('shop.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a><span class="sep">✦</span>
        @endif
        {{ $product->name }}
    </div>

    <div class="pd-grid">
        <div class="pd-gallery reveal in">
            <div class="pd-main"><img src="{{ $product->gallery[0] }}" alt="{{ $product->name }}"></div>
            @if (count($product->gallery) > 1)
                <div class="pd-thumbs">
                    @foreach ($product->gallery as $i => $src)
                        <button type="button" data-src="{{ $src }}" @class(['on' => $i === 0]) aria-label="View image {{ $i + 1 }}">
                            <img src="{{ $src }}" alt="{{ $product->name }} view {{ $i + 1 }}">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="pd-info reveal in">
            @if ($product->category)
                <div class="eyebrow">{{ $product->category->name }}</div>
            @endif
            <h1 class="display">{{ $product->name }}</h1>
            <div class="stars">{{ str_repeat('★', (int) round($product->rating_avg)) }}{{ str_repeat('☆', 5 - (int) round($product->rating_avg)) }}<small>({{ $product->reviews_count }} {{ Str::plural('review', $product->reviews_count) }})</small></div>

            <div class="pd-price-row">
                <span class="pd-price">{{ price_format($product->current_price) }}</span>
                @if ($product->has_discount)
                    <span class="pd-price-old">{{ price_format($product->price) }}</span>
                    <span class="pd-save">Save {{ $product->discount_percent }}%</span>
                @endif
            </div>

            @if ($product->stock <= 0)
                <span class="stock-badge out">Out of stock</span>
            @elseif ($product->stock <= 5)
                <span class="stock-badge low">Only {{ $product->stock }} left</span>
            @else
                <span class="stock-badge">In stock</span>
            @endif

            @if ($product->description)
                <p class="pd-desc">{{ $product->description }}</p>
            @endif

            @if ($product->in_stock)
                <form class="pd-form" method="POST" action="{{ route('cart.add', $product) }}">
                    @csrf
                    <div class="qty-box">
                        <button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
                        <input type="number" name="qty" value="1" min="1" max="{{ $product->stock }}" aria-label="Quantity">
                        <button type="button" data-step="1" aria-label="Increase quantity">+</button>
                    </div>
                    <button type="submit" class="btn btn-solid">Add to cart
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/></svg>
                    </button>
                    <button type="submit" form="wishForm" class="wish-btn @if(auth()->check() && auth()->user()->hasInWishlist($product)) on @endif" aria-label="Toggle wishlist">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20s-7-4.5-9-9c-1.2-2.8.6-6 3.7-6C8.8 5 10.5 6.4 12 8c1.5-1.6 3.2-3 5.3-3 3.1 0 4.9 3.2 3.7 6-2 4.5-9 9-9 9Z"/></svg>
                    </button>
                </form>
            @else
                <div class="pd-form">
                    <button type="submit" form="wishForm" class="wish-btn @if(auth()->check() && auth()->user()->hasInWishlist($product)) on @endif" aria-label="Toggle wishlist" style="min-height:53px">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20s-7-4.5-9-9c-1.2-2.8.6-6 3.7-6C8.8 5 10.5 6.4 12 8c1.5-1.6 3.2-3 5.3-3 3.1 0 4.9 3.2 3.7 6-2 4.5-9 9-9 9Z"/></svg>
                    </button>
                    <span class="form-note" style="align-self:center">Add to wishlist and we will keep it for you.</span>
                </div>
            @endif
            <form id="wishForm" method="POST" action="{{ route('wishlist.toggle', $product) }}">@csrf</form>

            <dl class="pd-meta">
                @if ($product->sku)
                    <div><dt>SKU</dt><dd>{{ $product->sku }}</dd></div>
                @endif
                @if ($product->category)
                    <div><dt>Category</dt><dd>{{ $product->category->name }}</dd></div>
                @endif
                <div><dt>Payment</dt><dd>Direct bank transfer</dd></div>
                <div><dt>Shipping</dt><dd>@if((float) setting('free_shipping_min', 150) > 0) Free over {{ price_format(setting('free_shipping_min', 150)) }} @else {{ price_format(setting('shipping_fee', 15)) }} flat @endif</dd></div>
            </dl>
        </div>
    </div>
</div>

@if ($product->details)
    <div class="container pd-section">
        <div class="eyebrow" style="margin-bottom:14px">Craft & materials</div>
        <h2 class="display">Details</h2>
        <div class="pd-details-body">{{ $product->details }}</div>
    </div>
@endif

<div class="container pd-section" id="reviews">
    <div class="eyebrow" style="margin-bottom:14px">Kind words</div>
    <h2 class="display">Reviews ({{ $reviews->count() }})</h2>

    @if ($reviews->isEmpty())
        <p style="color:var(--moss);margin-bottom:36px">No reviews yet — be the first to share your thoughts.</p>
    @else
        <div class="review-list">
            @foreach ($reviews as $review)
                <div class="review-card">
                    <div class="review-head">
                        <div>
                            <div class="stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                            <b>{{ $review->user?->name ?? 'Anonymous' }}</b> <span style="font-size:12px;color:var(--champagne);letter-spacing:.1em">✦ VERIFIED BUYER</span>
                        </div>
                        <time>{{ $review->created_at->format('M j, Y') }}</time>
                    </div>
                    @if ($review->comment)
                        <p>{{ $review->comment }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @guest
        <p class="form-note"><a href="{{ route('login') }}">Sign in</a> to review this piece after purchasing it.</p>
    @else
        @if ($hasReviewed)
            <p class="form-note">You have already reviewed this piece — thank you. Reviews appear once approved.</p>
        @elseif ($canReview)
            <div class="review-form-card">
                <h3>Share your experience</h3>
                <form method="POST" action="{{ route('product.review', $product) }}">
                    @csrf
                    <div class="field">
                        <label>Your rating</label>
                        <div class="rating-input">
                            @for ($i = 5; $i >= 1; $i--)
                                <input type="radio" name="rating" value="{{ $i }}" id="star{{ $i }}" @checked(old('rating') == $i) required>
                                <label for="star{{ $i }}" aria-label="{{ $i }} stars">★</label>
                            @endfor
                        </div>
                        @error('rating')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="comment">Your review</label>
                        <textarea id="comment" name="comment" placeholder="What did you love about it?">{{ old('comment') }}</textarea>
                        @error('comment')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="btn btn-solid">Submit review</button>
                </form>
            </div>
        @else
            <p class="form-note">Only verified buyers can review this piece.</p>
        @endif
    @endguest
</div>

@if ($related->isNotEmpty())
    <section class="products" style="padding:96px 0">
        <div class="container">
            <div class="section-head">
                <div>
                    <div class="eyebrow">You may also love</div>
                    <h2 class="display" style="margin-top:16px">Related pieces</h2>
                </div>
                <a href="{{ route('shop.index') }}" class="link-more">View all products</a>
            </div>
            <div class="prod-grid">
                @foreach ($related as $rel)
                    <x-product-card :product="$rel" />
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection
