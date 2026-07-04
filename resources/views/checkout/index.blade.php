@extends('layouts.app')

@section('title', 'Checkout — '.setting('store_name', 'Eluxee'))

@section('content')
<div class="page-strip">
    <div class="container">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a><span class="sep">✦</span><a href="{{ route('cart.index') }}">Cart</a><span class="sep">✦</span>Checkout</div>
        <h1 class="display">Checkout</h1>
    </div>
</div>

<div class="container checkout-wrap">
    @if ($errors->any())
        <div class="reject-note" style="margin-bottom:26px">
            Please review the highlighted fields below.
        </div>
    @endif

    <form method="POST" action="{{ route('checkout.place') }}" enctype="multipart/form-data">
        @csrf
        <div class="checkout-grid">
            <div>
                {{-- 1 · Shipping --}}
                <section class="co-section">
                    <h2><span class="co-num">1</span> Shipping details</h2>
                    <p class="co-sub">Where should we deliver your pieces?</p>
                    <div class="form-grid">
                        <div class="field">
                            <label for="ship_name">Full name</label>
                            <input id="ship_name" type="text" name="ship_name" value="{{ old('ship_name', $defaultAddress?->name ?? auth()->user()->name) }}" required>
                            @error('ship_name')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="ship_phone">Phone</label>
                            <input id="ship_phone" type="text" name="ship_phone" value="{{ old('ship_phone', $defaultAddress?->phone ?? auth()->user()->phone) }}" required>
                            @error('ship_phone')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field full">
                            <label for="ship_address">Street address</label>
                            <input id="ship_address" type="text" name="ship_address" value="{{ old('ship_address', $defaultAddress?->address) }}" required>
                            @error('ship_address')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="ship_city">City</label>
                            <input id="ship_city" type="text" name="ship_city" value="{{ old('ship_city', $defaultAddress?->city) }}" required>
                            @error('ship_city')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="ship_state">State / region</label>
                            <input id="ship_state" type="text" name="ship_state" value="{{ old('ship_state', $defaultAddress?->state) }}">
                        </div>
                        <div class="field">
                            <label for="ship_postal">Postal code</label>
                            <input id="ship_postal" type="text" name="ship_postal" value="{{ old('ship_postal', $defaultAddress?->postal_code) }}">
                        </div>
                        <div class="field">
                            <label for="ship_country">Country</label>
                            <input id="ship_country" type="text" name="ship_country" value="{{ old('ship_country', $defaultAddress?->country) }}" required>
                            @error('ship_country')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field full">
                            <label for="order_note">Order note (optional)</label>
                            <textarea id="order_note" name="order_note" style="min-height:70px" placeholder="Gift wrap, ring size, delivery instructions…">{{ old('order_note') }}</textarea>
                        </div>
                    </div>
                    <label class="checkbox-row">
                        <input type="checkbox" name="save_address" value="1" @checked(old('save_address'))>
                        Save this address for next time
                    </label>
                </section>

                {{-- 2 · Bank transfer --}}
                <section class="co-section">
                    <h2><span class="co-num">2</span> Pay by bank transfer</h2>
                    <p class="co-sub">Transfer the order total to one of our accounts below, then upload your payment receipt.</p>

                    @if ($bankAccounts->isEmpty())
                        <div class="reject-note">Online payment is temporarily unavailable — no bank account is configured. Please contact us to complete your order.</div>
                    @else
                        <div class="bank-list">
                            @foreach ($bankAccounts as $i => $account)
                                <label class="bank-card @if(old('bank_account_id', $bankAccounts->first()->id) == $account->id) selected @endif">
                                    <input type="radio" name="bank_account_id" value="{{ $account->id }}" @checked(old('bank_account_id', $bankAccounts->first()->id) == $account->id) required>
                                    <div class="bc-head">
                                        <span class="bc-bank">{{ $account->bank_name }}</span>
                                        <span class="bc-radio"></span>
                                    </div>
                                    <dl class="bank-details">
                                        <div><dt>Account name</dt><dd>{{ $account->account_name }}</dd></div>
                                        <div><dt>Account number</dt><dd>{{ $account->account_number }}</dd></div>
                                        @if ($account->iban)
                                            <div><dt>IBAN</dt><dd>{{ $account->iban }}</dd></div>
                                        @endif
                                        @if ($account->swift_code)
                                            <div><dt>SWIFT</dt><dd>{{ $account->swift_code }}</dd></div>
                                        @endif
                                        @if ($account->branch)
                                            <div><dt>Branch</dt><dd>{{ $account->branch }}</dd></div>
                                        @endif
                                        @if ($account->instructions)
                                            <span class="bd-instructions">{{ $account->instructions }}</span>
                                        @endif
                                    </dl>
                                </label>
                            @endforeach
                        </div>
                        @error('bank_account_id')<span class="field-error">{{ $message }}</span>@enderror

                        <div class="pay-note">
                            ✦ Transfer exactly <b>{{ price_format($total) }}</b> and keep your receipt. Your order ships once our team verifies the payment — usually within a few hours.
                        </div>
                    @endif
                </section>

                {{-- 3 · Payment proof --}}
                <section class="co-section">
                    <h2><span class="co-num">3</span> Upload payment screenshot</h2>
                    <p class="co-sub">A clear photo or screenshot of the completed transfer. JPG, PNG or WEBP, max 5&nbsp;MB.</p>

                    <div class="upload-box @if($errors->has('payment_screenshot')) has-file @endif" id="uploadBox">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M12 16V4m0 0 4 4m-4-4L8 8"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                        <div class="up-title">Click to upload your transfer receipt</div>
                        <div class="up-hint">This is required to finalise your order</div>
                        <img id="uploadPreview" class="upload-preview" alt="Payment screenshot preview">
                        <input type="file" name="payment_screenshot" accept="image/jpeg,image/png,image/webp">
                    </div>
                    @error('payment_screenshot')<span class="field-error" style="display:block;margin-top:10px">{{ $message }}</span>@enderror

                    <div class="field" style="margin-top:22px;margin-bottom:0">
                        <label for="payment_reference">Transaction reference (optional)</label>
                        <input id="payment_reference" type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="e.g. TRX-8842105">
                    </div>
                </section>
            </div>

            {{-- Order summary --}}
            <aside class="summary-card">
                <h3>Your order</h3>
                <div class="co-items">
                    @foreach ($items as $line)
                        <div class="co-item">
                            <img src="{{ $line['product']->image_url }}" alt="{{ $line['product']->name }}">
                            <div>
                                <div class="ci-name">{{ $line['product']->name }}</div>
                                <div class="ci-qty">Qty {{ $line['qty'] }}</div>
                            </div>
                            <span class="ci-price">{{ price_format($line['line_total']) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="sum-row"><span>Subtotal</span><b>{{ price_format($subtotal) }}</b></div>
                @if ($discount > 0)
                    <div class="sum-row"><span>Discount ({{ $coupon->code }})</span><b>−{{ price_format($discount) }}</b></div>
                @endif
                <div class="sum-row"><span>Shipping</span>@if ($shipping == 0)<span class="free">Free</span>@else<b>{{ price_format($shipping) }}</b>@endif</div>
                @if ($tax > 0)
                    <div class="sum-row"><span>Tax</span><b>{{ price_format($tax) }}</b></div>
                @endif
                <div class="sum-row total"><span>Total to transfer</span><span>{{ price_format($total) }}</span></div>

                <button type="submit" class="btn btn-solid" @if($bankAccounts->isEmpty()) disabled style="opacity:.6" @endif>
                    Place order
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
                <div class="free-ship-note">By placing this order you confirm the transfer has been made.</div>
            </aside>
        </div>
    </form>
</div>
@endsection
