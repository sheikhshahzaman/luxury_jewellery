@extends('layouts.admin')

@section('title', 'Live rates')

@php
    $instruments = [
        'XAU' => ['Gold spot', 'USD per troy ounce'],
        'XAG' => ['Silver spot', 'USD per troy ounce'],
        'USDPKR' => ['US dollar', 'PKR per USD'],
    ];
    $fmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $when = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone(config('app.timezone')) : null;
    $provider = ['gold_api' => 'gold-api.com', 'goldapi_io' => 'goldapi.io'][config('rates.provider')] ?? config('rates.provider');
@endphp

@section('content')
<div class="adm-toolbar">
    <span style="color:var(--moss);font-size:13.5px">Spot prices come from the market; your buy and sell prices are only ever what you save here.</span>
    <a href="{{ route('rates.index') }}" target="_blank" class="btn-sm ghost">View rates page ↗</a>
</div>

{{-- 1 · Spot rates ---------------------------------------------------------- --}}
<form class="adm-form" method="POST" action="{{ route('admin.rates.spot') }}" id="spotForm">
    @csrf
    <div class="adm-panel">
        <h2>Spot rates</h2>

        <div class="spot-mode" role="radiogroup" aria-label="Where spot rates come from">
            <label class="mode-opt">
                <input type="radio" name="spot_mode" value="auto" @checked(old('spot_mode', $mode) === 'auto')>
                <span><b>Automatic</b> Fetched from {{ $provider }} every {{ config('rates.fetch_every_minutes') }} minutes</span>
            </label>
            <label class="mode-opt">
                <input type="radio" name="spot_mode" value="manual" @checked(old('spot_mode', $mode) === 'manual')>
                <span><b>Manual</b> You type the spot rates in; fetching pauses</span>
            </label>
        </div>

        <div class="adm-table-wrap" style="border:none;margin-top:16px">
            <table class="adm-table spot-table">
                <thead>
                    <tr><th>Rate</th><th>Bid</th><th>Ask</th><th>Updated</th><th>Source</th></tr>
                </thead>
                <tbody>
                    @foreach ($instruments as $code => [$name, $unit])
                        @php $s = $spot[$code] ?? null; @endphp
                        <tr>
                            <th scope="row">{{ $name }} <small class="muted">{{ $unit }}</small></th>
                            <td>
                                <span class="auto-only">{{ $fmt($s['bid'] ?? null) }}</span>
                                <input class="manual-only" type="number" step="0.0001" min="0" name="spot[{{ $code }}][bid]" value="{{ old("spot.$code.bid", $s['bid'] ?? '') }}" aria-label="{{ $name }} bid">
                            </td>
                            <td>
                                <span class="auto-only">{{ $fmt($s['ask'] ?? null) }}</span>
                                <input class="manual-only" type="number" step="0.0001" min="0" name="spot[{{ $code }}][ask]" value="{{ old("spot.$code.ask", $s['ask'] ?? '') }}" aria-label="{{ $name }} ask">
                            </td>
                            <td>
                                @if ($t = $when($s['updated_at'] ?? null))
                                    <time datetime="{{ $t->toIso8601String() }}" title="{{ $t->format('d M Y, H:i:s') }}">{{ $t->diffForHumans() }}</time>
                                @else
                                    <span class="muted">never</span>
                                @endif
                            </td>
                            <td class="muted">{{ $s['source'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($mode === 'auto')
            @if ($status['error'])
                <p class="spot-status is-bad">The last fetch{{ $status['attempted_at'] ? ' ('.$status['attempted_at']->diffForHumans().')' : '' }} had a problem, so the last good values are still shown: {{ $status['error'] }}</p>
            @elseif ($status['attempted_at'])
                <p class="spot-status">Last fetched {{ $status['attempted_at']->diffForHumans() }}.</p>
            @else
                <p class="spot-status is-bad">Nothing has been fetched yet. Automatic rates need a cron job on the server running <code>php artisan schedule:run</code> every minute (on Hostinger: hPanel → Advanced → Cron Jobs). You can also fetch now.</p>
            @endif
        @endif
        <p class="muted manual-only" style="font-size:12.5px;margin-top:8px">Bid and ask can be the same. The dollar rate is optional; without it the page shows spot in dollars only.</p>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
            <button type="submit" class="btn-sm">Save spot settings</button>
            @if ($mode === 'auto')
                <button type="submit" form="fetchNow" class="btn-sm ghost">Fetch now</button>
            @endif
        </div>
    </div>
</form>
<form id="fetchNow" method="POST" action="{{ route('admin.rates.fetch') }}" hidden>@csrf</form>

{{-- 2 · Buy / sell prices --------------------------------------------------- --}}
<form class="adm-form" method="POST" action="{{ route('admin.rates.update') }}" id="ratesForm">
    @csrf

    @foreach (\App\Models\MetalRate::METALS as $metal => $title)
        <div class="adm-panel">
            <h2>{{ $title }} <span class="muted" style="font-size:13px;letter-spacing:0;text-transform:none">· buy &amp; sell in PKR</span></h2>
            <div class="adm-table-wrap" style="border:none">
                <table class="adm-table rates-edit" data-metal="{{ $metal }}">
                    <thead>
                        <tr><th>Size</th><th>Note</th><th>Qty</th><th>Unit</th><th>Buy (Rs)</th><th>Sell (Rs)</th><th>Order</th><th>Shown</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($rates->get($metal, collect()) as $rate)
                            <tr>
                                <td>
                                    <input type="hidden" name="rows[{{ $rate->id }}][metal]" value="{{ $metal }}">
                                    <input type="text" name="rows[{{ $rate->id }}][label]" value="{{ old("rows.$rate->id.label", $rate->label) }}" required placeholder="1 Tola">
                                    <code class="rate-key" title="Stable key used by the website and its API">{{ $rate->key }}</code>
                                </td>
                                <td><input type="text" name="rows[{{ $rate->id }}][note]" value="{{ old("rows.$rate->id.note", $rate->note) }}" placeholder="Bar, 999…"></td>
                                <td style="width:90px"><input type="number" step="0.001" min="0" name="rows[{{ $rate->id }}][quantity]" value="{{ old("rows.$rate->id.quantity", rtrim(rtrim($rate->quantity, '0'), '.')) }}" required></td>
                                <td style="width:100px">
                                    <select name="rows[{{ $rate->id }}][unit]">
                                        @foreach (\App\Models\MetalRate::UNITS as $value => $name)
                                            <option value="{{ $value }}" @selected(old("rows.$rate->id.unit", $rate->unit) === $value)>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="1" min="0" name="rows[{{ $rate->id }}][buy]" value="{{ old("rows.$rate->id.buy", (int) $rate->buy) }}" required></td>
                                <td>
                                    <input type="number" step="1" min="0" name="rows[{{ $rate->id }}][sell]" value="{{ old("rows.$rate->id.sell", (int) $rate->sell) }}" required>
                                    <small class="rate-meta">{{ $rate->updated_at?->diffForHumans() }}@if ($rate->editor) · {{ $rate->editor->name }}@endif</small>
                                </td>
                                <td style="width:80px"><input type="number" min="0" name="rows[{{ $rate->id }}][sort_order]" value="{{ $rate->sort_order }}"></td>
                                <td style="width:70px"><input type="hidden" name="rows[{{ $rate->id }}][is_active]" value="0"><input type="checkbox" name="rows[{{ $rate->id }}][is_active]" value="1" @checked($rate->is_active) aria-label="Shown on the rates page"></td>
                                <td style="text-align:right"><button type="submit" form="delete-rate-{{ $rate->id }}" class="btn-sm danger">Delete</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn-sm ghost" data-add-rate="{{ $metal }}" style="margin-top:12px">+ Add {{ strtolower(explode(' ', $title)[0]) }} item</button>
        </div>
    @endforeach

    <div class="adm-panel">
        <div class="field full">
            <label for="rates_note">Note under the rates</label>
            <textarea id="rates_note" name="rates_note" style="min-height:60px">{{ old('rates_note', setting('rates_note', 'Rates are indicative and move with the market. Your final price is confirmed when you place your order.')) }}</textarea>
        </div>
    </div>

    <div style="display:flex;gap:12px;position:sticky;bottom:20px">
        <button type="submit" class="btn-sm" style="box-shadow:0 10px 30px -10px rgba(30,41,33,.5)">Save prices</button>
    </div>
</form>

{{-- 3 · Recent changes ------------------------------------------------------- --}}
@if ($changes->isNotEmpty())
    <div class="adm-panel" style="margin-top:28px">
        <h2>Recent price changes</h2>
        <div class="adm-table-wrap" style="border:none">
            <table class="adm-table">
                <thead><tr><th>When</th><th>Item</th><th>Buy</th><th>Sell</th><th>By</th></tr></thead>
                <tbody>
                    @foreach ($changes as $change)
                        <tr>
                            <td title="{{ $change->created_at?->format('d M Y, H:i:s') }}">{{ $change->created_at?->diffForHumans() }}</td>
                            <td><code class="rate-key">{{ $change->key }}</code></td>
                            <td>@if ($change->buy_before !== null)<span class="muted">{{ number_format($change->buy_before) }} →</span> @endif{{ number_format($change->buy) }}</td>
                            <td>@if ($change->sell_before !== null)<span class="muted">{{ number_format($change->sell_before) }} →</span> @endif{{ number_format($change->sell) }}</td>
                            <td>{{ $change->user?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@foreach ($rates->flatten() as $rate)
    <form id="delete-rate-{{ $rate->id }}" method="POST" action="{{ route('admin.rates.destroy', $rate) }}" onsubmit="return confirm({{ Js::from('Remove '.$rate->displayLabel().' from the rates board?') }})" hidden>
        @csrf @method('DELETE')
    </form>
@endforeach

<template id="rateRowTemplate">
    <tr>
        <td>
            <input type="hidden" name="rows[__KEY__][metal]" value="__METAL__">
            <input type="text" name="rows[__KEY__][label]" required placeholder="1 Tola">
            <input type="text" name="rows[__KEY__][key]" class="rate-key-input" placeholder="key (optional), e.g. __METAL___1_tola" pattern="[a-z0-9_]+" aria-label="Key">
        </td>
        <td><input type="text" name="rows[__KEY__][note]" placeholder="Bar, 999…"></td>
        <td style="width:90px"><input type="number" step="0.001" min="0" name="rows[__KEY__][quantity]" value="1" required></td>
        <td style="width:100px">
            <select name="rows[__KEY__][unit]">
                @foreach (\App\Models\MetalRate::UNITS as $value => $name)
                    <option value="{{ $value }}">{{ $name }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" step="1" min="0" name="rows[__KEY__][buy]" required></td>
        <td><input type="number" step="1" min="0" name="rows[__KEY__][sell]" required></td>
        <td style="width:80px"><input type="number" min="0" name="rows[__KEY__][sort_order]" value="99"></td>
        <td style="width:70px"><input type="hidden" name="rows[__KEY__][is_active]" value="0"><input type="checkbox" name="rows[__KEY__][is_active]" value="1" checked aria-label="Shown on the rates page"></td>
        <td style="text-align:right"><button type="button" class="btn-sm ghost" data-remove-new>Remove</button></td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
    (() => {
        const tpl = document.getElementById('rateRowTemplate').innerHTML;
        let n = 0;
        document.querySelectorAll('[data-add-rate]').forEach(btn => btn.addEventListener('click', () => {
            const metal = btn.dataset.addRate;
            const body = document.querySelector(`.rates-edit[data-metal="${metal}"] tbody`);
            body.insertAdjacentHTML('beforeend', tpl.replaceAll('__KEY__', 'new-' + (++n)).replaceAll('__METAL__', metal));
            body.lastElementChild.querySelector('input[type=text]').focus();
        }));
        document.addEventListener('click', e => {
            if (e.target.matches('[data-remove-new]')) e.target.closest('tr').remove();
        });

        // Spot: show the typed inputs only in manual mode.
        const form = document.getElementById('spotForm');
        const sync = () => {
            const manual = form.querySelector('input[name=spot_mode]:checked')?.value === 'manual';
            form.classList.toggle('is-manual', manual);
            form.querySelectorAll('.manual-only').forEach(el => { if ('disabled' in el) el.disabled = !manual; });
        };
        form.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush

@push('styles')
<style>
    .rates-edit input[type=text],.rates-edit input[type=number],.rates-edit select,.spot-table input{width:100%;min-width:90px;padding:9px 11px;border:1px solid var(--line);border-radius:10px;background:var(--ivory);font:inherit}
    .rates-edit input:focus,.rates-edit select:focus,.spot-table input:focus{outline:none;border-color:var(--champagne)}
    .rate-key{display:inline-block;margin-top:5px;font-size:11.5px;color:var(--moss);background:rgba(30,41,33,.06);padding:2px 6px;border-radius:6px}
    .rates-edit .rate-key-input{margin-top:6px;font-size:12px;padding:6px 9px}
    .rate-meta{display:block;margin-top:5px;font-size:11.5px;color:var(--moss);white-space:nowrap}
    .spot-mode{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px}
    .mode-opt{display:flex;gap:10px;align-items:flex-start;padding:14px 16px;border:1px solid var(--line);border-radius:14px;cursor:pointer}
    .mode-opt:has(input:checked){border-color:var(--champagne);background:rgba(195,165,114,.08)}
    .mode-opt input{margin-top:3px;accent-color:var(--forest)}
    .mode-opt span{font-size:13.5px;color:var(--moss);line-height:1.45}
    .mode-opt b{display:block;font-weight:500;color:var(--forest);font-size:14.5px}
    .spot-table th small{display:block;font-weight:400;font-size:11.5px}
    #spotForm .manual-only{display:none}
    #spotForm.is-manual .manual-only{display:block}
    #spotForm.is-manual .auto-only{display:none}
    .spot-status{margin-top:12px;font-size:13px;color:var(--moss)}
    .spot-status.is-bad{color:#9b3b3b}
</style>
@endpush
