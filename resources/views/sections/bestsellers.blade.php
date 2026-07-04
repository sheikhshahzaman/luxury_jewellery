<section class="products" id="shop">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <div class="eyebrow">{{ setting('bestsellers_eyebrow', 'Most loved') }}</div>
                <h2 class="display" style="margin-top:16px">{{ setting('bestsellers_title', 'Best sellers') }}</h2>
            </div>
            <a href="{{ route('shop.index') }}" class="link-more">View all products</a>
        </div>
        <div class="prod-grid">
            @foreach ($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </div>
</section>
