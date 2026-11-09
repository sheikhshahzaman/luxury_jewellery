<header class="nav-wrap">
    <div class="container">
        <nav class="nav">
            <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Open menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a href="{{ route('home') }}" class="brand" aria-label="{{ setting('store_name', 'Lax Jewellers') }} — home">
                @include('partials.brand')
            </a>
            <ul class="nav-links">
                <li><a href="{{ route('home') }}" @class(['active' => request()->routeIs('home')])>Home</a></li>
                <li><a href="{{ route('shop.index') }}" @class(['active' => (request()->routeIs('shop.*') && ! request('category')) || request()->routeIs('product.*')])>Shop</a></li>
                @if ($navCategories->isNotEmpty())
                    <li class="nav-dropdown" data-dropdown>
                        <button type="button" class="nav-drop-btn @if(request('category')) active @endif" aria-expanded="false" aria-controls="categoryMenu">
                            Categories
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="nav-menu" id="categoryMenu">
                            @foreach ($navCategories as $navCategory)
                                <a href="{{ route('shop.index', ['category' => $navCategory->slug]) }}" @class(['on' => request('category') === $navCategory->slug])>
                                    <img src="{{ $navCategory->thumb_url }}" alt="" width="38" height="38">
                                    <span class="nm-name">{{ $navCategory->name }}</span>
                                    <span class="nm-count">{{ $navCategory->count }}</span>
                                </a>
                            @endforeach
                            <a href="{{ route('shop.index') }}" class="nm-all">Shop everything</a>
                        </div>
                    </li>
                @endif
                <li><a href="{{ route('rates.index') }}" @class(['nav-rates', 'active' => request()->routeIs('rates.*')])><span class="nav-live" aria-hidden="true"></span>Live rates</a></li>
                <li><a href="{{ route('page.offers') }}" @class(['active' => request()->routeIs('page.offers')])>Offers</a></li>
                <li><a href="{{ route('page.about') }}" @class(['active' => request()->routeIs('page.about')])>Our Story</a></li>
                <li><a href="{{ route('page.contact') }}" @class(['active' => request()->routeIs('page.contact')])>Contact</a></li>
            </ul>

            <div class="nav-actions">
                <button class="icon-btn" id="searchToggle" aria-label="Search">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </button>
                @auth
                    <a class="icon-btn account-icon" href="{{ route('account.wishlist') }}" aria-label="Wishlist">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20s-7-4.5-9-9c-1.2-2.8.6-6 3.7-6C8.8 5 10.5 6.4 12 8c1.5-1.6 3.2-3 5.3-3 3.1 0 4.9 3.2 3.7 6-2 4.5-9 9-9 9Z"/></svg>
                    </a>
                    <a class="icon-btn account-icon" href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('account.dashboard') }}" aria-label="Account">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.6-3.4 4.6-5 8-5s6.4 1.6 8 5"/></svg>
                    </a>
                @else
                    <a class="icon-btn account-icon" href="{{ route('login') }}" aria-label="Sign in">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.6-3.4 4.6-5 8-5s6.4 1.6 8 5"/></svg>
                    </a>
                @endauth
                <a class="icon-btn" href="{{ route('cart.index') }}" aria-label="Cart">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/></svg>
                    <span class="cart-count" id="cartCount">{{ cart_count() }}</span>
                </a>
            </div>
        </nav>
    </div>
    <div class="search-bar" id="searchBar">
        <form class="container search-form" action="{{ route('shop.index') }}" method="GET" autocomplete="off">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" id="searchInput" value="{{ request('q') }}" placeholder="Search rings, necklaces, earrings…" aria-label="Search products"
                   data-suggest-url="{{ route('search.suggest') }}">
            <button type="submit" class="search-go">Search</button>
            <div class="search-suggest" id="searchSuggest" role="listbox" aria-label="Suggestions"></div>
        </form>
    </div>
    <div class="mobile-menu" id="mobileMenu">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('shop.index') }}">Shop</a>
        @if ($navCategories->isNotEmpty())
            <details class="mobile-cats">
                <summary>Categories</summary>
                @foreach ($navCategories as $navCategory)
                    <a href="{{ route('shop.index', ['category' => $navCategory->slug]) }}">{{ $navCategory->name }} <span>{{ $navCategory->count }}</span></a>
                @endforeach
            </details>
        @endif
        <a href="{{ route('rates.index') }}">Live gold &amp; silver rates</a>
        <a href="{{ route('page.offers') }}">Offers</a>
        <a href="{{ route('page.about') }}">Our Story</a>
        <a href="{{ route('page.contact') }}">Contact</a>
        @auth
            <a href="{{ route('account.dashboard') }}">My Account</a>
            <a href="{{ route('account.wishlist') }}">Wishlist</a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}">Admin Panel</a>
            @endif
        @else
            <a href="{{ route('login') }}">Sign in</a>
            <a href="{{ route('register') }}">Create account</a>
        @endauth
    </div>
</header>
