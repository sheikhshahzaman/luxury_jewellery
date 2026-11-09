@extends('layouts.admin')

@section('title', $account->exists ? 'Edit payment account' : 'Add payment account')

@section('content')
<form class="adm-form" method="POST"
      action="{{ $account->exists ? route('admin.bank-accounts.update', $account) : route('admin.bank-accounts.store') }}">
    @csrf
    @if ($account->exists) @method('PUT') @endif

    <div class="adm-panel">
        <h2>Account details — shown to customers at checkout</h2>
        @php $type = old('type', $account->type ?? 'bank'); @endphp
        <div class="form-grid" id="accountFields" data-type="{{ $type }}">
            <div class="field">
                <label for="type">Payment type</label>
                <select id="type" name="type" required onchange="document.getElementById('accountFields').dataset.type=this.value">
                    @foreach (\App\Models\BankAccount::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="bank_name"><span class="if-bank">Bank name</span><span class="if-wallet">Wallet name shown to customers</span></label>
                <input id="bank_name" type="text" name="bank_name" value="{{ old('bank_name', $account->bank_name) }}" required placeholder="Meezan Bank / JazzCash">
            </div>
            <div class="field">
                <label for="account_name">Account holder name</label>
                <input id="account_name" type="text" name="account_name" value="{{ old('account_name', $account->account_name) }}" required>
            </div>
            <div class="field">
                <label for="account_number"><span class="if-bank">Account number</span><span class="if-wallet">Wallet mobile number</span></label>
                <input id="account_number" type="text" name="account_number" value="{{ old('account_number', $account->account_number) }}" required placeholder="03XX-XXXXXXX for wallets">
            </div>
            <div class="field if-bank">
                <label for="iban">IBAN (optional)</label>
                <input id="iban" type="text" name="iban" value="{{ old('iban', $account->iban) }}">
            </div>
            <div class="field if-bank">
                <label for="swift_code">SWIFT code (optional)</label>
                <input id="swift_code" type="text" name="swift_code" value="{{ old('swift_code', $account->swift_code) }}">
            </div>
            <div class="field if-bank">
                <label for="branch">Branch (optional)</label>
                <input id="branch" type="text" name="branch" value="{{ old('branch', $account->branch) }}">
            </div>
            <div class="field full">
                <label for="instructions">Transfer instructions (optional, shown under the account)</label>
                <textarea id="instructions" name="instructions" placeholder="Use your order name as the transfer reference…">{{ old('instructions', $account->instructions) }}</textarea>
            </div>
            <div class="field">
                <label for="sort_order">Sort order</label>
                <input id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $account->sort_order ?? 0) }}">
            </div>
        </div>
        <label class="switch-row">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->exists ? $account->is_active : true))>
            Active — offer this account at checkout
        </label>
    </div>

    <div style="display:flex;gap:12px">
        <button type="submit" class="btn-sm">{{ $account->exists ? 'Save changes' : 'Add account' }}</button>
        <a href="{{ route('admin.bank-accounts.index') }}" class="btn-sm ghost">Cancel</a>
    </div>
</form>
@endsection

@push('styles')
<style>
    #accountFields:not([data-type="bank"]) .if-bank{display:none}
    #accountFields[data-type="bank"] .if-wallet{display:none}
</style>
@endpush
