@extends('layouts.admin')

@section('title', 'Reviews')

@section('content')
<div class="adm-toolbar">
    <form method="GET" action="{{ route('admin.reviews.index') }}">
        <select name="status" onchange="this.form.submit()">
            <option value="">All reviews</option>
            <option value="pending" @selected(request('status') === 'pending')>Awaiting approval</option>
            <option value="approved" @selected(request('status') === 'approved')>Approved</option>
        </select>
    </form>
</div>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th>Product</th><th>Customer</th><th>Rating</th><th style="min-width:280px">Comment</th><th>Date</th><th>Status</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($reviews as $review)
                <tr>
                    <td>
                        @if ($review->product)
                            <a class="row-link" href="{{ route('product.show', $review->product) }}" target="_blank">{{ $review->product->name }}</a>
                        @else
                            <span class="muted">Deleted product</span>
                        @endif
                    </td>
                    <td class="muted">{{ $review->user?->name ?? '—' }}</td>
                    <td><span class="stars" style="font-size:12px">{{ str_repeat('★', $review->rating) }}</span></td>
                    <td class="muted" style="max-width:340px">{{ Str::limit($review->comment, 140) ?: '—' }}</td>
                    <td class="muted">{{ $review->created_at->format('M j, Y') }}</td>
                    <td><span class="badge {{ $review->is_approved ? 'b-verified' : 'b-pending' }}">{{ $review->is_approved ? 'Live' : 'Pending' }}</span></td>
                    <td>
                        <div class="actions">
                            @if ($review->is_approved)
                                <form method="POST" action="{{ route('admin.reviews.unapprove', $review) }}">
                                    @csrf
                                    <button type="submit" class="btn-sm ghost">Hide</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">
                                    @csrf
                                    <button type="submit" class="btn-sm success">Approve</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" onsubmit="return confirm('Delete this review?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="adm-empty"><span class="gem">✦</span>No reviews here.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px">{{ $reviews->links('partials.pager') }}</div>
@endsection
