@extends('layouts.admin')

@section('title', $account->exists ? 'Edit bank account' : 'Add bank account')

@section('content')
<form class="adm-form" method="POST"
      action="{{ $account->exists ? route('admin.bank-accounts.update', $account) : route('admin.bank-accounts.store') }}">
    @csrf
    @if ($account->exists) @method('PUT') @endif

    <div class="adm-panel">
        <h2>Account details — shown to customers at checkout</h2>
        <div class="form-grid">
            <div class="field">
                <label for="bank_name">Bank name</label>
                <input id="bank_name" type="text" name="bank_name" value="{{ old('bank_name', $account->bank_name) }}" required placeholder="Meezan Bank">
            </div>
            <div class="field">
                <label for="account_name">Account holder name</label>
                <input id="account_name" type="text" name="account_name" value="{{ old('account_name', $account->account_name) }}" required>
            </div>
            <div class="field">
                <label for="account_number">Account number</label>
                <input id="account_number" type="text" name="account_number" value="{{ old('account_number', $account->account_number) }}" required>
            </div>
            <div class="field">
                <label for="iban">IBAN (optional)</label>
                <input id="iban" type="text" name="iban" value="{{ old('iban', $account->iban) }}">
            </div>
            <div class="field">
                <label for="swift_code">SWIFT code (optional)</label>
                <input id="swift_code" type="text" name="swift_code" value="{{ old('swift_code', $account->swift_code) }}">
            </div>
            <div class="field">
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
