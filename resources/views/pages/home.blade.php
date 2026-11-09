@extends('layouts.app')

@section('title', setting('store_name', 'Lax Jewellers').' — Fine Jewelry, Handcrafted Since 1999')

@push('preload')
    {{-- The opening film is above the fold: fetch its player alongside the page. --}}
    @foreach (vite_chunks('resources/js/hero-frames.js') as $url)
        <link rel="modulepreload" href="{{ $url }}">
    @endforeach
@endpush

@section('content')
    @include('sections.story')
    @include('sections.marquee')
    @include('sections.categories')
    @include('sections.bestsellers')
    @include('sections.banner')
    @include('sections.values')
    @include('sections.editorial')
    @include('sections.testimonials')
    @include('sections.gallery')
    @include('sections.newsletter')
@endsection
