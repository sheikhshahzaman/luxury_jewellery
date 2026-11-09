<section id="collections">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <div class="eyebrow">Curated for you</div>
                <h2 class="display" style="margin-top:16px">{{ setting('categories_title', 'Shop by category') }}</h2>
            </div>
            <a href="{{ route('shop.index') }}" class="link-more">View all products</a>
        </div>
        <div class="cat-grid">
            @foreach ($categories as $category)
                <a href="{{ route('shop.index', ['category' => $category->slug]) }}" class="cat-card reveal">
                    <div class="arch"><img src="{{ $category->image_url }}" alt="{{ $category->name }}" loading="lazy"></div>
                    <span class="cat-arrow">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 17 17 7M9 7h8v8"/></svg>
                    </span>
                    <div class="cat-meta">
                        <h3>{{ $category->name }}</h3>
                        <span>{{ $category->count > 0 ? $category->count.' '.Str::plural('piece', $category->count) : 'Coming soon' }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
