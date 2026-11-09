{{-- The home page's opening: a pinned section where scrolling plays a film of the
     collection (frames in public/media/hero, played by resources/js/hero-frames.js).
     Chapter 0 is the hero; chapters 1–4 each give one reason to buy from us, using
     live store data; chapter 5 is the finale. With reduced motion it reads as a
     plain hero + list over the film's first frame. --}}
@php
    // Live 1-tola gold rate for the "gold rate" chapter.
    try {
        $goldTola = app(\App\Services\LiveRates::class)->headline('gold');
    } catch (\Throwable) {
        $goldTola = null;
    }
    $paymentMethods = array_filter(explode(' · ', payment_methods_summary()));
    $freeShipping = (float) setting('free_shipping_min', 150);

    $chapters = [
        ['title' => setting('story_1_title', 'Made your way'), 'text' => setting('story_1_text', 'Pick your metal and size, and add a personal engraving. The price updates as you choose, before anything goes in your cart.')],
        ['title' => setting('story_2_title', "Today's gold rate, in the open"), 'text' => setting('story_2_text', 'We publish our gold and silver rates on the site, so you can check what gold is selling for any time you like.')],
        ['title' => setting('story_3_title', 'Pay the way you like'), 'text' => setting('story_3_text', 'Pay by transfer or wallet and upload your receipt, or simply pay in cash when your order arrives.')],
        ['title' => setting('story_4_title', 'Delivered and tracked'), 'text' => setting('story_4_text', 'Follow every order from placed to delivered in your account, with each step marked as it happens.')],
        ['title' => setting('story_5_title', 'Your metal'), 'text' => setting('story_5_text', 'Many pieces come in gold, rose gold or silver. Choose yours on each product.')],
    ];

    // Frame sets for the scroll film; the poster is only needed here in the markup.
    $frames = hero_frames();
    $framesData = array_map(fn ($f) => \Illuminate\Support\Arr::except($f, 'poster'), $frames);
@endphp
<section class="anx anx-hero" data-story aria-labelledby="story-title">
    <div class="anx-pin">
        <div class="anx-stage" data-story-stage @if ($frames) data-frames="{{ json_encode($framesData) }}" @endif aria-hidden="true">
            @if ($frames)
                <picture>
                    @isset($frames['tall'])<source media="(max-aspect-ratio: 9/10)" srcset="{{ $frames['tall']['poster'] }}">@endisset
                    <img class="anx-poster" src="{{ ($frames['wide'] ?? $frames['tall'])['poster'] }}" alt="" fetchpriority="high">
                </picture>
            @else
                <img class="anx-poster" src="{{ setting_image('hero_image', 'https://images.unsplash.com/photo-1573408301185-9146fe634ad0?w=900&q=80') }}" alt="">
            @endif
        </div>

        <div class="container anx-ui">
            <ol class="anx-chapters">
                {{-- Chapter 0: the hero --}}
                <li data-anx-chapter class="anx-intro-chapter is-active">
                    <div class="eyebrow">{{ setting('hero_eyebrow', 'New Season · '.date('Y')) }}</div>
                    <h1 class="display" id="story-title">{!! setting('hero_title', 'Jewelry that keeps your <em>finest</em> moments.') !!}</h1>
                    <p>{{ setting('hero_text', 'Handcrafted rings, necklaces, earrings and bangles in gold and silver. Designed to be worn daily, kept for decades.') }}</p>
                    <div class="hero-ctas">
                        <a class="btn btn-gold" href="{{ route('shop.index') }}">Shop the collection
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        </a>
                        <a class="btn btn-light" href="{{ route('rates.index') }}">Today's gold rate</a>
                    </div>
                </li>

                @foreach ($chapters as $i => $chapter)
                    <li data-anx-chapter>
                        <h2>{{ $chapter['title'] }}</h2>
                        <p>{{ $chapter['text'] }}</p>

                        @switch($i)
                            @case(0)
                                <ul class="anx-chips"><li>Metal</li><li>Size</li><li>Engraving</li></ul>
                                @break
                            @case(1)
                                @if ($goldTola)
                                    {{-- Updated live by the rates poller in eluxee.js --}}
                                    <a href="{{ route('rates.index') }}" class="anx-rate" data-ticker-row="{{ $goldTola['key'] }}">
                                        <span class="rt-sym" aria-hidden="true">Au</span>
                                        <span>{{ $goldTola['size'] }} gold</span>
                                        <b data-ticker-price>Rs {{ number_format($goldTola['buy']) }}</b>
                                        <span class="rt-dir" aria-hidden="true"></span>
                                        <span class="anx-rate-link">All rates</span>
                                    </a>
                                @endif
                                @break
                            @case(2)
                                <ul class="anx-chips">@foreach ($paymentMethods as $method)<li>{{ $method }}</li>@endforeach</ul>
                                @break
                            @case(3)
                                <ul class="anx-chips">
                                    @if ($freeShipping > 0)<li>Free shipping over {{ price_format($freeShipping) }}</li>@endif
                                    <li>Order tracking</li>
                                </ul>
                                @break
                            @case(4)
                                <a href="{{ route('shop.index') }}" class="btn btn-gold anx-cta">Shop the collection
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                                </a>
                                @break
                        @endswitch
                    </li>
                @endforeach
            </ol>

            <div class="anx-progress" aria-hidden="true"><span data-anx-progress></span></div>
            <p class="anx-hint" aria-hidden="true"><span></span>Scroll</p>
        </div>
    </div>
</section>
