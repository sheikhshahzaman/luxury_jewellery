<div class="marquee" aria-hidden="true">
    <div class="marquee-track">
        @php
            $marqueeItems = array_filter(array_map('trim', explode('|', setting('marquee_text', 'Fine Jewelry|Handcrafted|Ethically Sourced|Recycled Gold|Since 1999'))));
        @endphp
        @for ($i = 0; $i < 2; $i++)
            <span>@foreach ($marqueeItems as $item){{ $item }} <i class="gem">✦</i> @endforeach</span>
        @endfor
    </div>
</div>
