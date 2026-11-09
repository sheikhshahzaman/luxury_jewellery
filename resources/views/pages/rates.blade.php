@extends('layouts.app')

@section('title', 'Gold & silver rates today — '.setting('store_name', 'Lax Jewellers'))
@section('meta_description', 'Live gold and silver spot prices, and today\'s buying and selling rates in rupees per tola, gram and kilogram.')

@push('styles')
    <link rel="stylesheet" href="{{ asset_v('css/rates.css') }}">
@endpush
@push('scripts')
    <script src="{{ asset_v('js/rates.js') }}" defer></script>
@endpush

@php
    $elements = [
        'gold' => ['sym' => 'Au', 'num' => 79, 'name' => 'Gold', 'pair' => 'XAU/USD', 'unit' => 'Tola / Gram rates'],
        'silver' => ['sym' => 'Ag', 'num' => 47, 'name' => 'Silver', 'pair' => 'XAG/USD', 'unit' => 'Tola / KG rates'],
    ];
    $rs = fn ($v) => 'Rs '.number_format($v);
    $usd = fn ($v) => '$'.number_format($v, 2);
    $local = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone(config('app.timezone')) : null;
    $boards = collect($snapshot['prices'])->groupBy('metal');
    $spotAt = $local($snapshot['spot']['updated_at']);
    $everyMinutes = (int) config('rates.fetch_every_minutes');
@endphp

@section('content')
<div class="rates-page" data-rates data-endpoint="{{ route('rates.live') }}" data-next-in="{{ $snapshot['poll_seconds'] * 1000 }}">

    {{-- 1 · Live spot prices --------------------------------------------------- --}}
    <section class="rates-hero" aria-labelledby="rates-title">
        <div class="container">
            <div class="rh-head">
                <div>
                    <p class="rh-kicker"><span class="live-dot" aria-hidden="true"></span>Live spot prices <span>per troy ounce</span></p>
                    <h1 class="display" id="rates-title">Gold &amp; silver rates today</h1>
                    <p class="rh-sub">International spot prices in dollars{{ $snapshot['spot']['mode'] === 'auto' ? ', updated every '.$everyMinutes.' minutes' : '' }}, and our own buying and selling rates in rupees.</p>
                </div>
                <div class="rh-clock" aria-live="off">
                    <svg class="tick-ring" viewBox="0 0 44 44" aria-hidden="true">
                        <circle cx="22" cy="22" r="19" class="tr-track"/>
                        <circle cx="22" cy="22" r="19" class="tr-fill" data-tick-ring/>
                    </svg>
                    <div>
                        <span class="rh-clock-label">Spot updated</span>
                        <time data-updated @if ($spotAt) datetime="{{ $spotAt->toIso8601String() }}" @endif>{{ $spotAt?->format('H:i') ?? '—' }}</time>
                    </div>
                </div>
            </div>

            <div class="spot-grid">
                @foreach ($elements as $metal => $el)
                    @php
                        $spot = $snapshot['spot'][$metal] ?? ['price' => 0, 'change' => 0, 'change_pct' => 0, 'history' => [], 'bid_usd_oz' => null, 'ask_usd_oz' => null, 'pkr_per_tola' => null, 'pkr_per_gram' => null];
                        $dir = $spot['change'] >= 0 ? 'up' : 'down';
                    @endphp
                    <article class="spot-card spot-{{ $metal }}" data-spot="{{ $metal }}">
                        <div class="element-tile" aria-hidden="true">
                            <span class="el-num">{{ $el['num'] }}</span>
                            <span class="el-sym">{{ $el['sym'] }}</span>
                            <span class="el-name">{{ $el['name'] }}</span>
                        </div>
                        <div class="spot-body">
                            <h2 class="spot-name">{{ $el['name'] }} <span>{{ $el['pair'] }}</span></h2>
                            <p class="spot-price" data-price aria-live="polite">{{ $spot['price'] ? $usd($spot['price']) : '—' }}</p>
                            <p class="spot-change is-{{ $dir }}" data-change>
                                <span class="arrow" aria-hidden="true"></span>
                                <span data-change-text>{{ ($spot['change'] >= 0 ? '+' : '−').$usd(abs($spot['change'])) }} ({{ number_format(abs($spot['change_pct']), 2) }}%)</span>
                                <span class="spot-period">24h</span>
                            </p>
                            <dl class="spot-meta">
                                <div @if ($spot['bid_usd_oz'] === $spot['ask_usd_oz']) hidden @endif data-bidask-row><dt>Bid / Ask</dt><dd data-bidask>{{ $spot['bid_usd_oz'] ? $usd($spot['bid_usd_oz']).' / '.$usd($spot['ask_usd_oz']) : '' }}</dd></div>
                                <div @unless ($spot['pkr_per_tola']) hidden @endunless data-pkr-row><dt>In rupees</dt><dd data-pkr>{{ $spot['pkr_per_tola'] ? $rs($spot['pkr_per_tola']).' / tola · '.$rs($spot['pkr_per_gram']).' / gram' : '' }}</dd></div>
                            </dl>
                        </div>
                        <svg class="spark" viewBox="0 0 320 90" preserveAspectRatio="none" aria-hidden="true" data-spark='@json($spot['history'])'>
                            <defs>
                                <linearGradient id="spark-{{ $metal }}" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="currentColor" stop-opacity=".35"/>
                                    <stop offset="1" stop-color="currentColor" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path class="spark-fill" fill="url(#spark-{{ $metal }})"/>
                            <path class="spark-line"/>
                            <circle class="spark-dot" r="4"/>
                        </svg>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 2 · Rupee rate boards ---------------------------------------------------- --}}
    <section class="rate-boards" aria-label="Rupee rates">
        <div class="container">
            <div class="boards-grid">
                @foreach ($elements as $metal => $el)
                    @continue($boards->get($metal, collect())->isEmpty())
                    @php $boardAt = $local($boards[$metal]->max('updated_at')); @endphp
                    <article class="board board-{{ $metal }}">
                        <header class="board-head">
                            <span class="board-sym" aria-hidden="true">{{ $el['sym'] }}</span>
                            <div>
                                <h2>{{ \App\Models\MetalRate::METALS[$metal] }}</h2>
                                <p>{{ $el['unit'] }}@if ($boardAt) · <span data-board-updated="{{ $metal }}">updated {{ $boardAt->format('j M, H:i') }}</span>@endif</p>
                            </div>
                            <span class="board-cur">PKR</span>
                        </header>
                        <table class="board-table">
                            <thead>
                                <tr><th scope="col">Size</th><th scope="col">Buy</th><th scope="col">Sell</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($boards[$metal] as $row)
                                    <tr data-row="{{ $row['key'] }}">
                                        <th scope="row">{{ $row['size'] }}@if ($row['note'])<small>{{ $row['note'] }}</small>@endif</th>
                                        <td><span class="rate" data-buy>{{ $rs($row['buy']) }}</span></td>
                                        <td><span class="rate" data-sell>{{ $rs($row['sell']) }}</span><span class="row-move" data-move aria-hidden="true"></span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </article>
                @endforeach
            </div>

            <div class="rates-foot">
                <dl class="rates-legend">
                    <div><dt>Buy</dt><dd>the price you pay us</dd></div>
                    <div><dt>Sell</dt><dd>the price we pay you</dd></div>
                    <div><dt>Spot</dt><dd>international market price; rupee figures use today's dollar rate and 11.6638 g per tola</dd></div>
                </dl>
                <p class="rates-note">{{ setting('rates_note', 'Rates are indicative and move with the market. Your final price is confirmed when you place your order.') }}</p>
                <div class="rates-cta">
                    @php $goldCategory = \App\Models\Category::active()->where('slug', 'gold-piece')->first(); @endphp
                    <a href="{{ $goldCategory ? route('shop.index', ['category' => $goldCategory->slug]) : route('shop.index') }}" class="btn btn-solid">Shop gold pieces</a>
                    <a href="{{ route('page.contact') }}" class="btn btn-ghost">Ask for a quote</a>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
