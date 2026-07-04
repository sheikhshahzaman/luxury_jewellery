@props(['product'])

<article class="prod-card reveal">
    <div class="prod-media">
        <a href="{{ route('product.show', $product) }}">
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">
        </a>
        @if ($product->tag)
            <span class="prod-tag">{{ $product->tag }}</span>
        @elseif ($product->has_discount)
            <span class="prod-tag">-{{ $product->discount_percent }}%</span>
        @endif

        @auth
            <form method="POST" action="{{ route('wishlist.toggle', $product) }}" class="card-wish">
                @csrf
                <button type="submit" class="card-wish-btn @if(auth()->user()->hasInWishlist($product)) on @endif"
                        aria-label="{{ auth()->user()->hasInWishlist($product) ? 'Remove from wishlist' : 'Add to wishlist' }}">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 20s-7-4.5-9-9c-1.2-2.8.6-6 3.7-6C8.8 5 10.5 6.4 12 8c1.5-1.6 3.2-3 5.3-3 3.1 0 4.9 3.2 3.7 6-2 4.5-9 9-9 9Z"/></svg>
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="card-wish" aria-label="Sign in to save to wishlist">
                <span class="card-wish-btn">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 20s-7-4.5-9-9c-1.2-2.8.6-6 3.7-6C8.8 5 10.5 6.4 12 8c1.5-1.6 3.2-3 5.3-3 3.1 0 4.9 3.2 3.7 6-2 4.5-9 9-9 9Z"/></svg>
                </span>
            </a>
        @endauth

        @if ($product->in_stock)
            <form method="POST" action="{{ route('cart.add', $product) }}">
                @csrf
                <button type="submit" class="quick-add">Add to cart</button>
            </form>
        @else
            <button type="button" class="quick-add" disabled style="opacity:.75">Out of stock</button>
        @endif
    </div>
    <div class="prod-info">
        <div class="stars">{{ str_repeat('★', (int) round($product->rating_avg)) }}{{ str_repeat('☆', 5 - (int) round($product->rating_avg)) }}<small>({{ $product->reviews_count }})</small></div>
        <h3 class="prod-name"><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></h3>
        <div class="prod-price-row">
            <span class="price">{{ price_format($product->current_price) }}</span>
            <span class="dots"></span>
            @if ($product->has_discount)
                <span class="price-old">{{ price_format($product->price) }}</span>
            @endif
        </div>
    </div>
</article>
