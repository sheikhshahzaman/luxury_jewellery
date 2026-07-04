@extends('layouts.app')

@section('title', 'Addresses — '.setting('store_name', 'Eluxee'))

@section('content')
<div class="container account-wrap">
    <div class="account-layout">
        @include('account.partials.side-nav')

        <div>
            <div class="account-head">
                <h1 class="display">Addresses</h1>
                <p>Saved shipping addresses for faster checkout.</p>
            </div>

            @foreach ($addresses as $address)
                <div class="panel">
                    <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
                        <div>
                            <h3 style="margin-bottom:6px">{{ $address->label ?: 'Address' }} @if($address->is_default)<span class="badge b-verified" style="margin-left:8px">Default</span>@endif</h3>
                            <p style="font-size:14px;color:var(--moss)">{{ $address->name }} · {{ $address->phone }}<br>{{ $address->one_line }}</p>
                        </div>
                        <div style="display:flex;gap:16px;align-items:center">
                            @unless ($address->is_default)
                                <form method="POST" action="{{ route('account.addresses.default', $address) }}">
                                    @csrf
                                    <button type="submit" class="link-quiet" style="background:none;border-top:none;border-left:none;border-right:none;cursor:pointer">Make default</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('account.addresses.delete', $address) }}" onsubmit="return confirm('Remove this address?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="cl-remove">Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="panel">
                <h3>Add new address</h3>
                <form method="POST" action="{{ route('account.addresses.store') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label for="label">Label</label>
                            <input id="label" type="text" name="label" value="{{ old('label') }}" placeholder="Home, Office…">
                        </div>
                        <div class="field">
                            <label for="a_name">Full name</label>
                            <input id="a_name" type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required>
                            @error('name')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="a_phone">Phone</label>
                            <input id="a_phone" type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}" required>
                            @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="a_country">Country</label>
                            <input id="a_country" type="text" name="country" value="{{ old('country') }}" required>
                            @error('country')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field full">
                            <label for="a_address">Street address</label>
                            <input id="a_address" type="text" name="address" value="{{ old('address') }}" required>
                            @error('address')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="a_city">City</label>
                            <input id="a_city" type="text" name="city" value="{{ old('city') }}" required>
                            @error('city')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="a_state">State / region</label>
                            <input id="a_state" type="text" name="state" value="{{ old('state') }}">
                        </div>
                        <div class="field">
                            <label for="a_postal">Postal code</label>
                            <input id="a_postal" type="text" name="postal_code" value="{{ old('postal_code') }}">
                        </div>
                    </div>
                    <label class="checkbox-row" style="margin-bottom:18px">
                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default'))>
                        Set as default address
                    </label>
                    <div>
                        <button type="submit" class="btn btn-solid">Save address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
