<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', setting('store_name', 'Eluxee').' — Fine Jewelry')</title>
    <meta name="description" content="@yield('meta_description', setting('store_tagline', 'Handcrafted fine jewelry in recycled 18k gold and ethically sourced stones.'))">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Jost:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/eluxee.css') }}">
    <link rel="stylesheet" href="{{ asset('css/shop.css') }}">
    @stack('styles')
</head>
<body>
    @include('partials.announcement')
    @include('partials.navbar')
    @include('partials.flash')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    <script src="{{ asset('js/eluxee.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
