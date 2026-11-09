{{-- Store logo: an uploaded logo (Settings → Brand) wins; otherwise the diamond mark + store name. --}}
@if (setting('logo'))
    <img src="{{ setting_image('logo') }}" alt="{{ setting('store_name', 'Lax Jewellers') }}" class="brand-logo">
@else
    @include('partials.brand-mark')
    <span class="brand-name">{{ setting('store_name', 'Lax Jewellers') }}</span>
@endif
