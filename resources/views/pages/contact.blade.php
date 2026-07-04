@extends('layouts.app')

@section('title', 'Contact — '.setting('store_name', 'Eluxee'))

@section('content')
<div class="page-strip">
    <div class="container">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a><span class="sep">✦</span>Contact</div>
        <h1 class="display">We are here to help</h1>
    </div>
</div>

<div class="container contact-grid">
    <div class="contact-info reveal in">
        <div class="eyebrow" style="margin-bottom:14px">Get in touch</div>
        <h2 class="display">Questions about a piece, an order, or a resize?</h2>
        <p>Write to us and our care team will reply within one business day. For order questions, include your order number.</p>
        <div class="contact-rows">
            @if (setting('contact_email'))
                <div>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    <div><span>Email</span>{{ setting('contact_email') }}</div>
                </div>
            @endif
            @if (setting('contact_phone'))
                <div>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
                    <div><span>Phone</span>{{ setting('contact_phone') }}</div>
                </div>
            @endif
            @if (setting('contact_address'))
                <div>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <div><span>Atelier</span>{{ setting('contact_address') }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="reveal in">
        <div class="co-section" style="margin-bottom:0">
            <h2 style="margin-bottom:20px">Send a message</h2>
            <form method="POST" action="{{ route('page.contact.submit') }}">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="name">Name</label>
                        <input id="name" type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" required>
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required>
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="phone">Phone (optional)</label>
                        <input id="phone" type="text" name="phone" value="{{ old('phone') }}">
                    </div>
                    <div class="field">
                        <label for="subject">Subject (optional)</label>
                        <input id="subject" type="text" name="subject" value="{{ old('subject') }}">
                    </div>
                    <div class="field full">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" required>{{ old('message') }}</textarea>
                        @error('message')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <button type="submit" class="btn btn-solid">Send message
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
            </form>
        </div>
    </div>
</div>

@php
    // Priority: explicit embed URL (or pasted iframe) → lat/lng via OpenStreetMap → address via Google.
    $mapRaw = trim((string) setting('map_embed_url'));
    if ($mapRaw && preg_match('/src="([^"]+)"/', $mapRaw, $m)) {
        $mapRaw = $m[1];
    }

    $lat = trim((string) setting('map_latitude'));
    $lng = trim((string) setting('map_longitude'));

    $mapUrl = null;
    $mapLink = null;
    if ($mapRaw !== '') {
        $mapUrl = $mapRaw;
    } elseif (is_numeric($lat) && is_numeric($lng)) {
        $d = 0.01;
        $bbox = ($lng - $d).','.($lat - $d).','.($lng + $d).','.($lat + $d);
        $mapUrl = 'https://www.openstreetmap.org/export/embed.html?bbox='.$bbox.'&layer=mapnik&marker='.$lat.','.$lng;
        $mapLink = 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lng.'#map=16/'.$lat.'/'.$lng;
    } elseif (setting('contact_address')) {
        $mapUrl = 'https://maps.google.com/maps?q='.urlencode(setting('contact_address')).'&z=14&output=embed';
        $mapLink = 'https://www.google.com/maps/search/?api=1&query='.urlencode(setting('contact_address'));
    }
@endphp
@if ($mapUrl)
    <div class="container" style="padding-bottom:96px">
        <div class="map-embed reveal">
            <iframe src="{{ $mapUrl }}" title="Store location" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>
        @if ($mapLink)
            <p class="form-note" style="margin-top:14px;text-align:right"><a href="{{ $mapLink }}" target="_blank" rel="noopener">Open in maps ↗</a></p>
        @endif
    </div>
@endif
@endsection
