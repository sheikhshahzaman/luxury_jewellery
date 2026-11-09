{{-- Metal / size / engraving pickers. Prices update live via eluxee.js. --}}
@php
    $metals = $product->metals();
    $sizes = $product->sizes();
    $oldMetal = old('metal', $metals[0]['name'] ?? null);
@endphp
<div class="pd-options" data-base-price="{{ $product->current_price }}" data-engraving-fee="{{ (float) $product->engraving_fee }}">
    @if ($metals)
        <fieldset class="opt-group">
            <legend>Metal <span class="opt-chosen" data-chosen="metal">{{ $oldMetal }}</span></legend>
            <div class="metal-swatches">
                @foreach ($metals as $metal)
                    <label class="metal-swatch" title="{{ $metal['name'] }}">
                        <input type="radio" name="metal" value="{{ $metal['name'] }}" data-price="{{ $metal['price'] }}" data-tone="{{ metal_tone($metal['name']) }}" @checked($oldMetal === $metal['name'])>
                        <span class="sw sw-{{ metal_tone($metal['name']) }}" aria-hidden="true"></span>
                        <span class="sw-name">{{ $metal['name'] }}</span>
                        @if ($metal['price'] != 0)
                            <span class="sw-price">{{ $metal['price'] > 0 ? '+' : '−' }}{{ price_format(abs($metal['price'])) }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
            @error('metal')<span class="field-error">{{ $message }}</span>@enderror
        </fieldset>
    @endif

    @if ($sizes)
        <fieldset class="opt-group">
            <legend>{{ $product->size_title }} <span class="opt-chosen" data-chosen="size">{{ old('size') }}</span></legend>
            <div class="size-chips">
                @foreach ($sizes as $size)
                    <label class="size-chip">
                        <input type="radio" name="size" value="{{ $size }}" @checked(old('size') === $size)>
                        <span>{{ $size }}</span>
                    </label>
                @endforeach
            </div>
            @error('size')<span class="field-error">{{ $message }}</span>@enderror
        </fieldset>
    @endif

    @if ($product->engraving_enabled)
        <div class="opt-group">
            <label class="opt-label" for="engraving">
                Personal engraving <small>(optional{{ $product->engraving_fee > 0 ? ', +'.price_format($product->engraving_fee) : '' }})</small>
            </label>
            <div class="engrave-field">
                <input id="engraving" type="text" name="engraving" maxlength="{{ \App\Models\Product::ENGRAVING_MAX }}" value="{{ old('engraving') }}" placeholder="Initials, a date, a word…" autocomplete="off">
                <span class="engrave-count"><span data-engrave-count>{{ mb_strlen((string) old('engraving')) }}</span>/{{ \App\Models\Product::ENGRAVING_MAX }}</span>
            </div>
            <div class="engrave-preview" aria-hidden="true"><span data-engrave-preview>{{ old('engraving') }}</span></div>
            @error('engraving')<span class="field-error">{{ $message }}</span>@enderror
        </div>
    @endif
</div>
