@extends('layouts.admin')

@section('title', 'Contact messages')

@section('content')
<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th></th><th>From</th><th>Subject</th><th style="min-width:300px">Message</th><th>Received</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($messages as $message)
                <tr @if(!$message->is_read) style="background:rgba(195,165,114,.07)" @endif>
                    <td>
                        <span class="badge {{ $message->is_read ? 'b-delivered' : 'b-pending' }}">{{ $message->is_read ? 'Read' : 'New' }}</span>
                    </td>
                    <td>
                        {{ $message->name }}
                        <div class="muted"><a href="mailto:{{ $message->email }}" class="row-link">{{ $message->email }}</a>@if($message->phone) · {{ $message->phone }}@endif</div>
                    </td>
                    <td class="muted">{{ $message->subject ?: '—' }}</td>
                    <td class="muted" style="max-width:380px">{{ $message->message }}</td>
                    <td class="muted">{{ $message->created_at->format('M j, Y H:i') }}</td>
                    <td>
                        <div class="actions">
                            <form method="POST" action="{{ route('admin.messages.read', $message) }}">
                                @csrf
                                <button type="submit" class="btn-sm ghost">{{ $message->is_read ? 'Mark new' : 'Mark read' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" onsubmit="return confirm('Delete this message?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="adm-empty"><span class="gem">✦</span>No messages.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px">{{ $messages->links('partials.pager') }}</div>
@endsection
