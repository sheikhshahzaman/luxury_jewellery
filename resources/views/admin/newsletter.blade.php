@extends('layouts.admin')

@section('title', 'Newsletter subscribers')

@section('content')
<div class="adm-toolbar">
    <span style="color:var(--moss);font-size:13.5px">{{ $total }} subscribers</span>
    <a href="{{ route('admin.newsletter.export') }}" class="btn-sm">Export CSV</a>
</div>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th>Email</th><th>Subscribed</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($subscribers as $subscriber)
                <tr>
                    <td>{{ $subscriber->email }}</td>
                    <td class="muted">{{ $subscriber->created_at->format('M j, Y H:i') }}</td>
                    <td>
                        <div class="actions">
                            <form method="POST" action="{{ route('admin.newsletter.destroy', $subscriber) }}" onsubmit="return confirm('Remove this subscriber?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-sm danger">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3"><div class="adm-empty"><span class="gem">✦</span>No subscribers yet.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px">{{ $subscribers->links('partials.pager') }}</div>
@endsection
