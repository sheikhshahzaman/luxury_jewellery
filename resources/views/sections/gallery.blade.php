@if (! empty($gallery))
<section style="padding-top:0">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <div class="eyebrow">On Instagram</div>
                <h2 class="display" style="margin-top:16px">{{ setting('instagram_handle', '@eluxee') }}</h2>
            </div>
            @if (setting('instagram_url'))
                <a href="{{ setting('instagram_url') }}" target="_blank" rel="noopener" class="link-more">Follow us</a>
            @endif
        </div>
        <div class="gallery-strip reveal">
            @foreach ($gallery as $image)
                <a href="{{ setting('instagram_url', '#') }}" @if(setting('instagram_url')) target="_blank" rel="noopener" @endif>
                    <img src="{{ $image }}" alt="Instagram" loading="lazy">
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
