<aside class="side-nav">
    <a href="{{ route('account.dashboard') }}" @class(['on' => request()->routeIs('account.dashboard')])>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="13" width="8" height="8" rx="2"/></svg>
        Overview
    </a>
    <a href="{{ route('account.orders') }}" @class(['on' => request()->routeIs('account.orders') || request()->routeIs('account.orders.show')])>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/></svg>
        Orders
    </a>
    <a href="{{ route('account.wishlist') }}" @class(['on' => request()->routeIs('account.wishlist')])>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20s-7-4.5-9-9c-1.2-2.8.6-6 3.7-6C8.8 5 10.5 6.4 12 8c1.5-1.6 3.2-3 5.3-3 3.1 0 4.9 3.2 3.7 6-2 4.5-9 9-9 9Z"/></svg>
        Wishlist
    </a>
    <a href="{{ route('account.addresses') }}" @class(['on' => request()->routeIs('account.addresses')])>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
        Addresses
    </a>
    <a href="{{ route('account.profile') }}" @class(['on' => request()->routeIs('account.profile')])>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.6-3.4 4.6-5 8-5s6.4 1.6 8 5"/></svg>
        Profile
    </a>
    @if (auth()->user()->isAdmin())
        <a href="{{ route('admin.dashboard') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3 4 7v5c0 4.4 3.2 8 8 9 4.8-1 8-4.6 8-9V7l-8-4Z"/></svg>
            Admin panel
        </a>
    @endif
    <a href="{{ route('logout') }}" onclick="event.preventDefault();document.getElementById('logoutForm').submit()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 6V5a2 2 0 0 1 2-2h7v18h-7a2 2 0 0 1-2-2v-1"/><path d="M3 12h11m0 0-3-3m3 3-3 3"/></svg>
        Sign out
    </a>
    <form id="logoutForm" method="POST" action="{{ route('logout') }}" style="display:none">@csrf</form>
</aside>
