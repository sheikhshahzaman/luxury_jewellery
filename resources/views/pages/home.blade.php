@extends('layouts.app')

@section('title', 'Eluxee — Fine Jewelry, Handcrafted Since 1999')

@section('content')
    @include('sections.hero')
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
