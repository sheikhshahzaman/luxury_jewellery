<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($sw = service_worker_url()) data-sw="{{ $sw }}" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <title>@yield('title', setting('store_name', 'Lax Jewellers').' — Fine Jewelry')</title>
    <meta name="description" content="@yield('meta_description', setting('store_tagline', 'Handcrafted fine jewelry in recycled 18k gold.'))">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (\App\Http\Middleware\SearchEngineVisibility::hidden())
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Jost:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset_v('css/eluxee.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/shop.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/experience.css') }}">
    @stack('styles')
    {{-- Early-download hints for the 3D chunks a page needs (see vite_chunks()). --}}
    @stack('preload')
</head>
<body>
    @include('partials.announcement')
    @include('partials.navbar')
    @include('partials.flash')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    <script src="{{ asset_v('js/eluxee.js') }}" defer></script>
    {{-- 3D + motion layer (npm run build). The store works without it. --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/js/storefront.js')
    @endif
    @stack('scripts')
</body>
</html>
