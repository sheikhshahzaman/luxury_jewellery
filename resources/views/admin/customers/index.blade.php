@extends('layouts.admin')

@section('title', 'Customers')

@section('content')
<div class="adm-toolbar">
    <form method="GET" action="{{ route('admin.customers.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or email…">
        <button type="submit" class="btn-sm ghost">Search</button>
    </form>
</div>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th>Customer</th><th>Phone</th><th>Joined</th><th>Orders</th><th>Spent (verified)</th><th>Status</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td>
                        <a class="row-link" href="{{ route('admin.customers.show', $customer) }}">{{ $customer->name }}</a>
                        <div class="muted">{{ $customer->email }}</div>
                    </td>
                    <td class="muted">{{ $customer->phone ?: '—' }}</td>
                    <td class="muted">{{ $customer->created_at->format('M j, Y') }}</td>
                    <td>{{ $customer->orders_count }}</td>
                    <td class="num">{{ price_format($customer->total_spent ?? 0) }}</td>
                    <td><span class="badge {{ $customer->is_blocked ? 'b-rejected' : 'b-verified' }}">{{ $customer->is_blocked ? 'Blocked' : 'Active' }}</span></td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="btn-sm ghost">View</a>
                            <form method="POST" action="{{ route('admin.customers.toggle-block', $customer) }}">
                                @csrf
                                <button type="submit" class="btn-sm {{ $customer->is_blocked ? 'success' : 'danger' }}">{{ $customer->is_blocked ? 'Unblock' : 'Block' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="adm-empty"><span class="gem">✦</span>No customers found.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px">{{ $customers->links('partials.pager') }}</div>
@endsection
