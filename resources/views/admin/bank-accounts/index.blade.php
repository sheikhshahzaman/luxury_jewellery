@extends('layouts.admin')

@section('title', 'Payment accounts')

@section('content')
<div class="adm-toolbar">
    <span style="color:var(--moss);font-size:13.5px">Bank, JazzCash and Easypaisa accounts shown at checkout. Only <b style="font-weight:500">active</b> accounts appear. Cash on delivery is switched on in <a class="row-link" href="{{ route('admin.settings') }}#payments">Settings</a>.</span>
    <a href="{{ route('admin.bank-accounts.create') }}" class="btn-sm">+ Add payment account</a>
</div>

@if ($accounts->where('is_active', true)->isEmpty())
    <div class="reject-note" style="margin-bottom:20px">No active payment account — customers can only check out with cash on delivery (if enabled). Add or activate one.</div>
@endif

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th>Type</th><th>Bank / wallet</th><th>Account name</th><th>Number / IBAN</th><th>Status</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td><span class="badge b-processing">{{ $account->type_label }}</span></td>
                    <td><a class="row-link" href="{{ route('admin.bank-accounts.edit', $account) }}">{{ $account->bank_name }}</a>
                        @if ($account->branch)<div class="muted">{{ $account->branch }}</div>@endif
                    </td>
                    <td>{{ $account->account_name }}</td>
                    <td>
                        {{ $account->account_number }}
                        @if ($account->iban)<div class="muted">{{ $account->iban }}</div>@endif
                    </td>
                    <td><span class="badge {{ $account->is_active ? 'b-verified' : 'b-cancelled' }}">{{ $account->is_active ? 'Active' : 'Hidden' }}</span></td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('admin.bank-accounts.edit', $account) }}" class="btn-sm ghost">Edit</a>
                            <form method="POST" action="{{ route('admin.bank-accounts.destroy', $account) }}" onsubmit="return confirm('Remove this payment account? If past orders used it, it will be hidden instead.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="adm-empty"><span class="gem">✦</span>No payment accounts yet — add one so customers can pay.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
