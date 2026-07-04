@extends('layouts.admin')

@section('title', 'Testimonials')

@section('content')
<div class="adm-toolbar">
    <span style="color:var(--moss);font-size:13.5px">Quotes shown in the "Kind words" section of the home page.</span>
    <a href="{{ route('admin.testimonials.create') }}" class="btn-sm">+ New testimonial</a>
</div>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr><th>Name</th><th>Location</th><th style="min-width:280px">Quote</th><th>Rating</th><th>Status</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($testimonials as $testimonial)
                <tr>
                    <td><a class="row-link" href="{{ route('admin.testimonials.edit', $testimonial) }}">{{ $testimonial->name }}</a></td>
                    <td class="muted">{{ $testimonial->location ?: '—' }}</td>
                    <td class="muted" style="max-width:360px">{{ Str::limit($testimonial->quote, 120) }}</td>
                    <td><span class="stars" style="font-size:12px">{{ str_repeat('★', $testimonial->rating) }}</span></td>
                    <td><span class="badge {{ $testimonial->is_active ? 'b-verified' : 'b-cancelled' }}">{{ $testimonial->is_active ? 'Live' : 'Hidden' }}</span></td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('admin.testimonials.edit', $testimonial) }}" class="btn-sm ghost">Edit</a>
                            <form method="POST" action="{{ route('admin.testimonials.destroy', $testimonial) }}" onsubmit="return confirm('Delete this testimonial?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="adm-empty"><span class="gem">✦</span>No testimonials yet.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
