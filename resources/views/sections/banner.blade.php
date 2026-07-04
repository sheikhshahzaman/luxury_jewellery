<section>
    <div class="container">
        <div class="banner-inner reveal">
            <div class="banner-copy">
                <div class="eyebrow">{{ setting('banner_eyebrow', 'Seasonal drop') }}</div>
                <h2 class="display">{{ setting('banner_title', 'The Gilded Hour Collection') }}</h2>
                <p>{{ setting('banner_text', 'Warm gold tones, soft silhouettes, and stones chosen for evening light. A limited run of 200 numbered pieces.') }}</p>
                <a href="{{ route('shop.index') }}" class="btn btn-light">Explore the collection
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
            </div>
            <div class="banner-media">
                <img src="{{ setting('banner_image', 'https://images.unsplash.com/photo-1602173574767-37ac01994b2a?w=1000&q=80') }}" alt="{{ setting('banner_title', 'Collection banner') }}">
                @if (setting('banner_pill'))
                    <span class="banner-pill">{{ setting('banner_pill') }}</span>
                @endif
            </div>
        </div>
    </div>
</section>
