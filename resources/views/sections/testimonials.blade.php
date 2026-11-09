@if ($testimonials->isNotEmpty())
{{-- Testimonial carousel: cards travel along a curved rail; the centre one grows into the spotlight. Behaviour in public/js/eluxee.js. --}}
<section class="tw" aria-roledescription="carousel" aria-labelledby="tw-title">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <div class="eyebrow">Kind words</div>
                <h2 class="display" id="tw-title" style="margin-top:16px">{{ setting('testimonials_title', 'Loved by our customers') }}</h2>
            </div>
            @if ($testimonials->count() > 1)
                <div class="tw-controls">
                    <span class="tw-count" aria-hidden="true"><b data-tw-current>1</b> / {{ $testimonials->count() }}</span>
                    <button type="button" class="tw-btn" data-tw-prev aria-label="Previous testimonial">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 12H5m6 6-6-6 6-6"/></svg>
                    </button>
                    <button type="button" class="tw-btn" data-tw-next aria-label="Next testimonial">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="tw-stage" data-testimonials tabindex="0" aria-label="Testimonials — use the arrow keys to move between them">
        @foreach ($testimonials as $i => $testimonial)
            <figure class="tw-card" data-tw-card aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $testimonials->count() }}">
                <svg class="tw-quote" viewBox="0 0 64 48" aria-hidden="true">
                    <path d="M26 6C14 9 6 18 6 30c0 7 5 12 11 12s10-4 10-10-4-10-10-10c-1 0-2 0-3 .3C15 16 20 11 27 9L26 6Zm32 0C46 9 38 18 38 30c0 7 5 12 11 12s10-4 10-10-4-10-10-10c-1 0-2 0-3 .3C47 16 52 11 59 9L58 6Z"/>
                </svg>
                <div class="tw-stars" role="img" aria-label="Rated {{ $testimonial->rating }} out of 5">
                    @for ($s = 0; $s < 5; $s++)
                        <span @class(['on' => $s < $testimonial->rating]) style="--i:{{ $s }}" aria-hidden="true">★</span>
                    @endfor
                </div>
                <blockquote data-tw-quote>{{ $testimonial->quote }}</blockquote>
                <figcaption>
                    <span class="tw-avatar" aria-hidden="true">{{ Str::upper(Str::substr($testimonial->name, 0, 1)) }}</span>
                    <span>
                        <b>{{ $testimonial->name }}</b>
                        @if ($testimonial->location)<small>{{ $testimonial->location }}</small>@endif
                    </span>
                </figcaption>
            </figure>
        @endforeach
    </div>

    @if ($testimonials->count() > 1)
        <div class="container"><div class="tw-progress" aria-hidden="true"><span data-tw-progress></span></div></div>
    @endif
</section>
@endif
