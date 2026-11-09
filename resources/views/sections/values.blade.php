@php
    $valueItems = [
        ['title' => setting('value_1_title', 'Ethically sourced'),        'text' => setting('value_1_text', 'Certified recycled 18k gold in every piece.')],
        ['title' => setting('value_2_title', 'Free worldwide shipping'),  'text' => setting('value_2_text', 'Insured express delivery on all orders over $150.')],
        ['title' => setting('value_3_title', 'Lifetime warranty'),        'text' => setting('value_3_text', 'Complimentary resizing, polishing and repairs, forever.')],
        ['title' => setting('value_4_title', 'Secure checkout'),          'text' => setting('value_4_text', 'Verified bank transfer with a simple upload-and-confirm flow.')],
    ];
    $valueIcons = [
        '<path d="M12 3 5 8l7 13 7-13-7-5Z"/><path d="M5 8h14M9.5 8 12 21 14.5 8"/>',
        '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
        '<path d="M12 3c2.5 2 5.5 3 8 3 0 7-3 11-8 15C7 17 4 13 4 6c2.5 0 5.5-1 8-3Z"/><path d="m9 12 2 2 4-4"/>',
        '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    ];
@endphp
<section style="padding-top:0">
    <div class="container">
        <div class="values-grid">
            @foreach ($valueItems as $i => $item)
                <div class="value-item reveal">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">{!! $valueIcons[$i] !!}</svg>
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
