<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <title>@yield('title', 'Admin') · {{ setting('store_name', 'Lax Jewellers') }} Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Jost:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset_v('css/eluxee.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/shop.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/admin.css') }}">
    @stack('styles')
</head>
<body class="admin">
    <aside class="adm-sidebar">
        <a href="{{ route('admin.dashboard') }}" class="adm-brand">{{ setting('store_name', 'Lax Jewellers') }}<span>Admin Panel</span></a>
        <nav class="adm-nav">
            <a href="{{ route('admin.dashboard') }}" @class(['on' => request()->routeIs('admin.dashboard')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="13" width="8" height="8" rx="2"/></svg>
                Dashboard
            </a>
            <div class="group">Catalog</div>
            <a href="{{ route('admin.products.index') }}" @class(['on' => request()->routeIs('admin.products.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3 4 7v10l8 4 8-4V7l-8-4Z"/><path d="M4 7l8 4 8-4M12 11v10"/></svg>
                Products
            </a>
            <a href="{{ route('admin.categories.index') }}" @class(['on' => request()->routeIs('admin.categories.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 5h7l2 3h7v11H4V5Z"/></svg>
                Categories
            </a>
            <div class="group">Sales</div>
            <a href="{{ route('admin.orders.index') }}" @class(['on' => request()->routeIs('admin.orders.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/></svg>
                Orders
                @php $pendingPay = \App\Models\Order::where('payment_status', 'submitted')->whereNotIn('status', ['cancelled'])->count(); @endphp
                @if ($pendingPay > 0)
                    <span class="pill">{{ $pendingPay }}</span>
                @endif
            </a>
            <a href="{{ route('admin.rates.index') }}" @class(['on' => request()->routeIs('admin.rates.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 17l5-5 4 4 8-8"/><path d="M15 8h5v5"/></svg>
                Live rates
            </a>
            <a href="{{ route('admin.coupons.index') }}" @class(['on' => request()->routeIs('admin.coupons.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 9V5h16v4a2 2 0 0 0 0 6v4H4v-4a2 2 0 0 0 0-6Z"/><path d="M12 5v14" stroke-dasharray="2 3"/></svg>
                Coupons
            </a>
            <a href="{{ route('admin.bank-accounts.index') }}" @class(['on' => request()->routeIs('admin.bank-accounts.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m12 3 9 6H3l9-6ZM5 9v8m5-8v8m4-8v8m5-8v8M3 21h18"/></svg>
                Payment accounts
            </a>
            <div class="group">People</div>
            <a href="{{ route('admin.customers.index') }}" @class(['on' => request()->routeIs('admin.customers.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.6-3.4 4.6-5 8-5s6.4 1.6 8 5"/></svg>
                Customers
            </a>
            <a href="{{ route('admin.reviews.index') }}" @class(['on' => request()->routeIs('admin.reviews.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3 15 9l7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1 3-6Z"/></svg>
                Reviews
                @php $pendingRev = \App\Models\Review::where('is_approved', false)->count(); @endphp
                @if ($pendingRev > 0)
                    <span class="pill">{{ $pendingRev }}</span>
                @endif
            </a>
            <div class="group">Content</div>
            <a href="{{ route('admin.testimonials.index') }}" @class(['on' => request()->routeIs('admin.testimonials.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 5h16v11H8l-4 4V5Z"/></svg>
                Testimonials
            </a>
            <a href="{{ route('admin.newsletter.index') }}" @class(['on' => request()->routeIs('admin.newsletter.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                Newsletter
            </a>
            <a href="{{ route('admin.messages.index') }}" @class(['on' => request()->routeIs('admin.messages.*')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 12a9 9 0 1 1-4.4-7.7L21 3l-1.2 4.5A9 9 0 0 1 21 12Z"/></svg>
                Messages
                @php $unread = \App\Models\ContactMessage::where('is_read', false)->count(); @endphp
                @if ($unread > 0)
                    <span class="pill">{{ $unread }}</span>
                @endif
            </a>
            <div class="group">System</div>
            <a href="{{ route('admin.settings') }}" @class(['on' => request()->routeIs('admin.settings')])>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.3 1a7 7 0 0 0-2.1-1.3L14 3h-4l-.5 2.6a7 7 0 0 0-2.1 1.3l-2.3-1-2 3.4 2 1.5a7 7 0 0 0 0 2.4l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 2.1 1.3L10 21h4l.5-2.6a7 7 0 0 0 2.1-1.3l2.3 1 2-3.4-2-1.5c.06-.4.1-.8.1-1.2Z"/></svg>
                Settings
            </a>
        </nav>
    </aside>

    <div class="adm-main">
        <header class="adm-topbar">
            <h1>@yield('title', 'Dashboard')</h1>
            <div class="right">
                <a href="{{ route('home') }}" target="_blank">View store ↗</a>
                <div class="adm-user">
                    <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span>{{ auth()->user()->name }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-sm ghost">Sign out</button>
                </form>
            </div>
        </header>

        <main class="adm-content">
            @include('partials.flash')
            @if ($errors->any())
                <div class="reject-note" style="margin-bottom:22px">
                    <b style="font-weight:500">Please fix the following:</b>
                    <ul style="margin:6px 0 0 18px;list-style:disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    <script src="{{ asset_v('js/eluxee.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
