@php $ticker = $tickerRates ?? []; @endphp
@if (setting('announcement') || $ticker)
    <div class="announce">
        <div class="container announce-inner">
            @if (setting('announcement'))
                <span class="announce-text">{{ setting('announcement') }}</span>
            @endif
            @if ($ticker)
                {{-- Live 1-tola rates; refreshed by the shared poller in eluxee.js. --}}
                <span class="rate-ticker" data-rate-ticker data-endpoint="{{ route('rates.live') }}" data-next-in="{{ $tickerNextIn }}">
                    <span class="rt-live" aria-hidden="true"></span>
                    @foreach ($ticker as $metal => $row)
                        <a href="{{ route('rates.index') }}" class="is-{{ $row['change'] >= 0 ? 'up' : 'down' }}" data-ticker-row="{{ $row['key'] }}">
                            <span class="rt-sym {{ $metal === 'silver' ? 'ag' : '' }}" aria-hidden="true">{{ $metal === 'silver' ? 'Ag' : 'Au' }}</span>
                            <span class="sr-only">{{ ucfirst($metal) }}</span>
                            {{ $row['size'] }}
                            <b data-ticker-price>Rs {{ number_format($row['buy']) }}</b>
                            <span class="rt-dir" aria-hidden="true"></span>
                        </a>
                    @endforeach
                </span>
            @endif
        </div>
    </div>
@endif
