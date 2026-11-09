@extends('layouts.app')

@section('title', 'Our Story — '.setting('store_name', 'Lax Jewellers'))

@section('content')
<div class="page-strip">
    <div class="container">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a><span class="sep">✦</span>Our story</div>
        <h1 class="display">{{ setting('about_title', 'Crafted by hand, worn for life.') }}</h1>
    </div>
</div>

<section class="editorial" style="border-top:none">
    <div class="container edit-grid">
        <div class="edit-visual reveal">
            <div class="arch"><img src="{{ setting_image('about_image', 'https://images.unsplash.com/photo-1506630448388-4e683c67ddb0?w=900&q=80') }}" alt="Atelier portrait"></div>
            <div class="edit-small"><img src="{{ setting_image('about_image_small', 'https://images.unsplash.com/photo-1610694955371-d4a3e0ce4b52?w=500&q=80') }}" alt="Jewelry detail"></div>
        </div>
        <div class="edit-copy reveal">
            <div class="eyebrow">{{ setting('about_eyebrow', 'Since 1999') }}</div>
            <h2 class="display">{{ setting('about_heading', 'Eleven pairs of hands, one piece of jewelry.') }}</h2>
            <p style="white-space:pre-line">{{ setting('about_text', 'Every piece begins as a sketch in our atelier and passes through eleven pairs of hands before it reaches yours. We cast in small batches, inspect every detail under magnification, and finish each surface by hand.

We believe jewelry should be made slowly, worn daily and kept for decades — never mass produced.') }}</p>
            <ul class="edit-points">
                <li><span class="gem">✦</span> Small batch casting, never mass produced</li>
                <li><span class="gem">✦</span> Every piece polished and inspected by hand</li>
                <li><span class="gem">✦</span> Signed and numbered certificate with every piece</li>
            </ul>
            <a href="{{ route('shop.index') }}" class="btn btn-ghost">Shop the collection
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>

@include('sections.values')
@endsection
