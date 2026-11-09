@extends('layouts.app')

@section('title', 'Checkout — '.setting('store_name', 'Lax Jewellers'))

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

    <form method="POST" action="{{ route('checkout.place') }}" enctype="multipart/form-data" id="checkoutForm">
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
                            <textarea id="order_note" name="order_note" style="min-height:70px" placeholder="Gift wrap, delivery instructions…">{{ old('order_note') }}</textarea>
                        </div>
                    </div>
                    <label class="checkbox-row">
                        <input type="checkbox" name="save_address" value="1" @checked(old('save_address'))>
                        Save this address for next time
                    </label>
                </section>

                {{-- 2 · Payment method --}}
                @php
                    $selectedMethod = old('payment_method', array_key_first($methods));
                    $methodHints = [
                        'bank' => 'Transfer to our bank account',
                        'jazzcash' => 'Send from your JazzCash wallet',
                        'easypaisa' => 'Send from your Easypaisa wallet',
                        'cod' => 'Pay in cash when it arrives',
                    ];
                @endphp
                <section class="co-section">
                    <h2><span class="co-num">2</span> Payment method</h2>
                    <p class="co-sub">Choose how you would like to pay. Online payments are confirmed by our team after you upload the receipt.</p>

                    @if ($methods === [])
                        <div class="reject-note">Checkout is temporarily unavailable — no payment method is configured. Please contact us to complete your order.</div>
                    @else
                        <div class="pay-methods" role="radiogroup" aria-label="Payment method">
                            @foreach ($methods as $method => $label)
                                <label class="pay-method">
                                    <input type="radio" name="payment_method" value="{{ $method }}" id="pm-{{ $method }}" @checked($selectedMethod === $method)>
                                    <span class="pm-icon pm-{{ $method }}" aria-hidden="true">
                                        @switch($method)
                                            @case('bank')<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m12 3 9 6H3l9-6ZM5 9v8m5-8v8m4-8v8m5-8v8M3 21h18"/></svg>@break
                                            @case('cod')<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9v.01M18 15v.01"/></svg>@break
                                            @default<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10.5 18.5h3"/></svg>
                                        @endswitch
                                    </span>
                                    <span class="pm-text"><b>{{ $label }}</b><small>{{ $methodHints[$method] ?? '' }}</small></span>
                                    <span class="bc-radio"></span>
                                </label>
                            @endforeach
                        </div>
                        @error('payment_method')<span class="field-error">{{ $message }}</span>@enderror

                        @foreach ($accountsByMethod as $method => $accounts)
                            @continue(! isset($methods[$method]))
                            <div class="pay-panel" data-method="{{ $method }}">
                                <p class="pp-title">Send {{ price_format($total) }} to {{ $accounts->count() > 1 ? 'one of these accounts' : 'this account' }}</p>
                                <div class="bank-list">
                                    @foreach ($accounts as $account)
                                        @php $isSelected = (string) old('bank_account_id', $accounts->first()->id) === (string) $account->id; @endphp
                                        <label @class(['bank-card', 'selected' => $isSelected])>
                                            <input type="radio" name="bank_account_id" value="{{ $account->id }}" @checked($isSelected && $selectedMethod === $method)>
                                            <div class="bc-head">
                                                <span class="bc-bank">{{ $account->display_name }}</span>
                                                <span class="bc-radio"></span>
                                            </div>
                                            <dl class="bank-details">
                                                <div><dt>{{ $account->isWallet() ? 'Account title' : 'Account name' }}</dt><dd>{{ $account->account_name }}</dd></div>
                                                <div><dt>{{ $account->isWallet() ? 'Mobile number' : 'Account number' }}</dt><dd>{{ $account->account_number }} <button type="button" class="copy-btn" data-copy="{{ $account->account_number }}">Copy</button></dd></div>
                                                @if ($account->iban)
                                                    <div><dt>IBAN</dt><dd>{{ $account->iban }} <button type="button" class="copy-btn" data-copy="{{ $account->iban }}">Copy</button></dd></div>
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
                            </div>
                        @endforeach
                        @error('bank_account_id')<span class="field-error">{{ $message }}</span>@enderror

                        @isset($methods['cod'])
                            <div class="pay-panel" data-method="cod">
                                <div class="pay-note">
                                    ✦ Pay <b>{{ price_format($total + $codFee) }}</b> in cash to the courier when your order arrives.
                                    @if ($codFee > 0) This includes a {{ price_format($codFee) }} cash-handling fee. @endif
                                    No receipt is needed — we will call to confirm before dispatch.
                                </div>
                            </div>
                        @endisset
                    @endif
                </section>

                {{-- 3 · Payment proof --}}
                <section class="co-section proof-section">
                    <h2><span class="co-num">3</span> Payment receipt</h2>
                    <p class="co-sub">After sending the payment, upload a screenshot of the receipt and enter its details. Your order stays <b style="font-weight:400">pending</b> until our team verifies it.</p>

                    <div class="form-grid">
                        <div class="field">
                            <label for="payment_reference">Transaction ID</label>
                            <input id="payment_reference" type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="e.g. 0123456789" autocomplete="off">
                            @error('payment_reference')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="payer_name">Sent from (account holder name)</label>
                            <input id="payer_name" type="text" name="payer_name" value="{{ old('payer_name', auth()->user()->name) }}">
                            @error('payer_name')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="upload-box @if($errors->has('payment_screenshot')) has-file @endif" id="uploadBox">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M12 16V4m0 0 4 4m-4-4L8 8"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                        <div class="up-title">Click to upload your payment receipt</div>
                        <div class="up-hint">Screenshot or photo · JPG, PNG or WEBP · max 5&nbsp;MB</div>
                        <img id="uploadPreview" class="upload-preview" alt="Payment screenshot preview">
                        <input type="file" name="payment_screenshot" accept="image/jpeg,image/png,image/webp">
                    </div>
                    @error('payment_screenshot')<span class="field-error" style="display:block;margin-top:10px">{{ $message }}</span>@enderror
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
                                @if ($line['option_lines'])
                                    <div class="opt-lines">@foreach ($line['option_lines'] as $opt)<span>{{ $opt }}</span>@endforeach</div>
                                @endif
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
                @if ($codFee > 0 && isset($methods['cod']))
                    <div class="sum-row cod-only"><span>Cash on delivery fee</span><b>{{ price_format($codFee) }}</b></div>
                @endif
                <div class="sum-row total"><span>Total</span><span id="coTotal" data-total="{{ price_format($total) }}" data-total-cod="{{ price_format($total + $codFee) }}">{{ price_format($selectedMethod === 'cod' ? $total + $codFee : $total) }}</span></div>

                <button type="submit" class="btn btn-solid" @if($methods === []) disabled style="opacity:.6" @endif>
                    Place order
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
                <div class="free-ship-note pay-online-only">By placing this order you confirm the payment has been sent.</div>
            </aside>
        </div>
    </form>
</div>
@endsection
