<section class="newsletter">
    <div class="container">
        <div class="eyebrow reveal">Join the circle</div>
        <h2 class="display reveal">{{ setting('newsletter_title', 'Receive first access to new pieces') }}</h2>
        <p class="reveal">{{ setting('newsletter_text', 'Early collection previews, private offers, and care guides. One email a month, nothing more.') }}</p>
        <form class="news-form reveal" method="POST" action="{{ route('newsletter.subscribe') }}">
            @csrf
            <input type="email" name="email" placeholder="Your email address" required aria-label="Email address" value="{{ old('email') }}">
            <button type="submit">Subscribe</button>
        </form>
        @error('email')
            <div class="news-note" style="color:#D9B3B3">{{ $message }}</div>
        @enderror
        <div class="news-note reveal">No spam. Unsubscribe anytime.</div>
    </div>
</section>
