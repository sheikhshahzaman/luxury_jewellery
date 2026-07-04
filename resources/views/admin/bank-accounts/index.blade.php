@extends('layouts.admin')

@section('title', 'Bank accounts')

@section('content')
<div class="adm-toolbar">
    <span style="color:var(--moss);font-size:13.5px">Accounts shown to customers at checkout. Only <b style="font-weight:500">active</b> accounts appear.</span>
    <a href="{{ route('admin.bank-accounts.create') }}" class="btn-sm">+ Add bank account</a>
</div>

@if ($accounts->where('is_active', true)->isEmpty())
    <div class="reject-note" style="margin-bottom:20px">No active bank account — customers currently cannot check out. Add or activate one.</div>
@endif

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th>Bank</th><th>Account name</th><th>Number / IBAN</th><th>Status</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
                <tr>
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
                            <form method="POST" action="{{ route('admin.bank-accounts.destroy', $account) }}" onsubmit="return confirm('Remove this bank account?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="adm-empty"><span class="gem">✦</span>No bank accounts yet — add one so customers can pay.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
