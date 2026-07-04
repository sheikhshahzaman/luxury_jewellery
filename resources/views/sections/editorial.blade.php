<section class="editorial" id="story">
    <div class="container edit-grid">
        <div class="edit-visual reveal">
            <div class="arch"><img src="{{ setting('about_image', 'https://images.unsplash.com/photo-1506630448388-4e683c67ddb0?w=900&q=80') }}" alt="Editorial portrait with rings"></div>
            <div class="edit-small"><img src="{{ setting('about_image_small', 'https://images.unsplash.com/photo-1600003263720-95b45a4035d5?w=500&q=80') }}" alt="Jewelry detail"></div>
        </div>
        <div class="edit-copy reveal">
            <div class="eyebrow">Our story</div>
            <h2 class="display">{{ setting('about_heading', 'Crafted by hand, worn for life.') }}</h2>
            <p style="white-space:pre-line">{{ Str::limit(setting('about_text', 'Every piece begins as a sketch in our atelier and passes through eleven pairs of hands before it reaches yours. We cast in small batches, set every stone under magnification, and finish each surface by hand.'), 260) }}</p>
            <ul class="edit-points">
                <li><span class="gem">✦</span> Small batch casting, never mass produced</li>
                <li><span class="gem">✦</span> Stones set and inspected under 10x magnification</li>
                <li><span class="gem">✦</span> Signed and numbered certificate with every piece</li>
            </ul>
            <a href="{{ route('page.about') }}" class="btn btn-ghost">Read the full story
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>
