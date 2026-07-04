<section class="hero">
    <div class="container hero-grid">
        <div class="reveal">
            <div class="eyebrow">{{ setting('hero_eyebrow', 'New Season · '.date('Y')) }}</div>
            <h1 class="display">{!! setting('hero_title', 'Jewelry that keeps your <em>finest</em> moments.') !!}</h1>
            <p>{{ setting('hero_text', 'Handcrafted rings, necklaces and earrings in recycled 18k gold and ethically sourced stones. Designed to be worn daily, kept for decades.') }}</p>
            <div class="hero-ctas">
                <a class="btn btn-solid" href="{{ route('shop.index') }}">Shop the collection
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
                <a class="btn btn-ghost" href="{{ route('page.about') }}">Our story</a>
            </div>
            <div class="hero-stats">
                <div><div class="stat-num">{{ setting('stat_1_num', '12k+') }}</div><div class="stat-label">{{ setting('stat_1_label', 'Pieces delivered') }}</div></div>
                <div><div class="stat-num">{{ setting('stat_2_num', '4.9') }}</div><div class="stat-label">{{ setting('stat_2_label', 'Average rating') }}</div></div>
                <div><div class="stat-num">{{ setting('stat_3_num', '25') }}</div><div class="stat-label">{{ setting('stat_3_label', 'Years of craft') }}</div></div>
            </div>
        </div>
        <div class="hero-visual reveal">
            <div class="arch">
                <img src="{{ setting('hero_image', 'https://images.unsplash.com/photo-1573408301185-9146fe634ad0?w=900&q=80') }}" alt="Hero visual">
            </div>
            <div class="float-badge">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 15 9l7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1 3-7Z"/></svg>
                Handcrafted
            </div>
            @if ($products->isNotEmpty())
                @php $heroProduct = $products->first(); @endphp
                <a class="float-card" href="{{ route('product.show', $heroProduct) }}">
                    <img src="{{ $heroProduct->image_url }}" alt="{{ $heroProduct->name }}">
                    <div>
                        <div class="fc-name">{{ $heroProduct->name }}</div>
                        <div class="fc-price">{{ price_format($heroProduct->current_price) }}</div>
                    </div>
                </a>
            @endif
        </div>
    </div>
</section>
