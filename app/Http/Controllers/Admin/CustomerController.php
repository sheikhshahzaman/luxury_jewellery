<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = User::where('role', 'customer')
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->where('payment_status', 'verified')->where('status', '!=', 'cancelled')], 'total')
            ->when($request->query('q'), function ($q, $term) {
                $q->where(fn ($sub) => $sub->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(User $customer): View
    {
        abort_if($customer->isAdmin(), 404);

        $customer->load(['addresses']);

        return view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $customer->orders()->latest()->paginate(10),
            'totalSpent' => $customer->orders()->where('payment_status', 'verified')->where('status', '!=', 'cancelled')->sum('total'),
        ]);
    }

    public function toggleBlock(User $customer): RedirectResponse
    {
        abort_if($customer->isAdmin(), 403);

        $customer->update(['is_blocked' => ! $customer->is_blocked]);

        return back()->with('success', $customer->is_blocked
            ? $customer->name.' has been blocked.'
            : $customer->name.' has been unblocked.');
    }
}
