@if ($testimonials->isNotEmpty())
<section>
    <div class="container">
        <div class="section-head reveal">
            <div>
                <div class="eyebrow">Kind words</div>
                <h2 class="display" style="margin-top:16px">{{ setting('testimonials_title', 'Loved by our customers') }}</h2>
            </div>
        </div>
        <div class="testi-grid">
            @foreach ($testimonials as $testimonial)
                <div class="testi-card reveal">
                    <div class="stars">{{ str_repeat('★', $testimonial->rating) }}{{ str_repeat('☆', 5 - $testimonial->rating) }}</div>
                    <blockquote>"{{ $testimonial->quote }}"</blockquote>
                    <div class="testi-who"><b>{{ $testimonial->name }}</b>@if($testimonial->location) · {{ $testimonial->location }}@endif</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
