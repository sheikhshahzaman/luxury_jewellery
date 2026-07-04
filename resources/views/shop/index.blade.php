@extends('layouts.app')

@section('title', ($activeCategory?->name ?? 'Shop').' — '.setting('store_name', 'Eluxee'))

@section('content')
<div class="page-strip">
    <div class="container">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a><span class="sep">✦</span>Shop @if($activeCategory)<span class="sep">✦</span>{{ $activeCategory->name }}@endif</div>
        <h1 class="display">{{ $activeCategory?->name ?? 'All jewelry' }}</h1>
    </div>
</div>

<div class="container">
    <div class="shop-layout">
        <aside class="filters">
            <div class="filter-block">
                <h3>Category</h3>
                <div class="filter-list">
                    <a href="{{ route('shop.index', request()->except(['category', 'page'])) }}" @class(['on' => ! $activeCategory])>All jewelry</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('shop.index', array_merge(request()->except('page'), ['category' => $category->slug])) }}"
                           @class(['on' => $activeCategory && $activeCategory->id === $category->id])>
                            {{ $category->name }} <span>{{ $category->count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="filter-block">
                <h3>Price</h3>
                <form method="GET" action="{{ route('shop.index') }}">
                    @foreach (request()->except(['min', 'max', 'page']) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <div class="price-inputs">
                        <input type="number" name="min" min="0" placeholder="Min" value="{{ request('min') }}" aria-label="Minimum price">
                        <span style="color:var(--moss)">—</span>
                        <input type="number" name="max" min="0" placeholder="Max" value="{{ request('max') }}" aria-label="Maximum price">
                    </div>
                    <button type="submit" class="btn btn-ghost filter-apply">Apply</button>
                </form>
            </div>
            @if (request()->hasAny(['q', 'min', 'max', 'category', 'sort']))
                <a href="{{ route('shop.index') }}" class="link-quiet">Clear all filters</a>
            @endif
        </aside>

        <div>
            @if (request('q'))
                <div class="chip-row">
                    <span class="chip">Search: <b>“{{ request('q') }}”</b> <a href="{{ route('shop.index', request()->except(['q', 'page'])) }}" aria-label="Clear search">×</a></span>
                </div>
            @endif

            <div class="shop-toolbar">
                <span class="results-count">{{ $products->total() }} {{ Str::plural('piece', $products->total()) }}</span>
                <form method="GET" action="{{ route('shop.index') }}" class="sort-form">
                    @foreach (request()->except(['sort', 'page']) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <select name="sort" onchange="this.form.submit()" aria-label="Sort products">
                        <option value="" @selected(!request('sort'))>Sort: Newest</option>
                        <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: low to high</option>
                        <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option>
                        <option value="name" @selected(request('sort') === 'name')>Name A–Z</option>
                        <option value="rating" @selected(request('sort') === 'rating')>Top rated</option>
                    </select>
                </form>
            </div>

            @if ($products->isEmpty())
                <div class="empty">
                    <span class="gem">✦</span>
                    <h3>No pieces found</h3>
                    <p>Try adjusting your filters or explore the full collection.</p>
                    <a href="{{ route('shop.index') }}" class="btn btn-solid">View all jewelry</a>
                </div>
            @else
                <div class="shop-grid">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                {{ $products->links('partials.pager') }}
            @endif
        </div>
    </div>
</div>
@endsection
