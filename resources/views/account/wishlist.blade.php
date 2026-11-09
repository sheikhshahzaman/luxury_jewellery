@extends('layouts.app')

@section('title', 'Wishlist — '.setting('store_name', 'Lax Jewellers'))

@section('content')
<div class="container account-wrap">
    <div class="account-layout">
        @include('account.partials.side-nav')

        <div>
            <div class="account-head">
                <h1 class="display">Wishlist</h1>
                <p>Pieces you are keeping an eye on.</p>
            </div>

            @if ($products->isEmpty())
                <div class="empty">
                    <span class="gem">✦</span>
                    <h3>Your wishlist is empty</h3>
                    <p>Tap the heart on any piece to save it here.</p>
                    <a href="{{ route('shop.index') }}" class="btn btn-solid">Explore the collection</a>
                </div>
            @else
                <div class="wish-grid">
                    @foreach ($products as $product)
                        <div style="position:relative">
                            <x-product-card :product="$product" />
                            <form method="POST" action="{{ route('wishlist.toggle', $product) }}" style="position:absolute;top:14px;right:14px;z-index:2">
                                @csrf
                                <button type="submit" class="wish-btn on" style="width:40px;height:40px;border-radius:50%" aria-label="Remove from wishlist">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5"><path d="M12 20s-7-4.5-9-9c-1.2-2.8.6-6 3.7-6C8.8 5 10.5 6.4 12 8c1.5-1.6 3.2-3 5.3-3 3.1 0 4.9 3.2 3.7 6-2 4.5-9 9-9 9Z"/></svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
