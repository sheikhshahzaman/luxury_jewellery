<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::with('user')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('payment'), fn ($q, $p) => $q->where('payment_status', $p))
            ->when($request->query('q'), function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('order_number', 'like', "%{$term}%")
                        ->orWhere('ship_name', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        $order->load(['items.product', 'user', 'bankAccount']);

        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Order::STATUSES)],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $newStatus = $data['status'];
        $oldStatus = $order->status;

        if ($newStatus !== $oldStatus) {
            if ($newStatus === 'cancelled') {
                $order->restoreStock();
                $order->cancelled_at = now();
            } elseif ($oldStatus === 'cancelled') {
                // Re-activating a cancelled order takes the stock again.
                foreach ($order->items as $item) {
                    if ($item->product && $item->product->stock < $item->quantity) {
                        return back()->with('error', 'Not enough stock to reactivate this order ('.$item->product_name.').');
                    }
                }
                foreach ($order->items as $item) {
                    $item->product?->decrement('stock', $item->quantity);
                }
                $order->cancelled_at = null;
            }

            if ($newStatus === 'shipped' && ! $order->shipped_at) {
                $order->shipped_at = now();
            }
            if ($newStatus === 'delivered' && ! $order->delivered_at) {
                $order->delivered_at = now();
            }
        }

        $order->status = $newStatus;
        $order->tracking_number = $data['tracking_number'] ?? $order->tracking_number;
        $order->admin_note = $data['admin_note'] ?? $order->admin_note;
        $order->save();

        return back()->with('success', 'Order updated to "'.ucfirst($newStatus).'".');
    }

    public function verifyPayment(Order $order): RedirectResponse
    {
        if ($order->payment_status === 'verified') {
            return back()->with('info', 'Payment is already verified.');
        }

        $order->update([
            'payment_status' => 'verified',
            'payment_verified_at' => now(),
            'payment_note' => null,
            'status' => $order->status === 'pending' ? 'processing' : $order->status,
        ]);

        return back()->with('success', 'Payment verified — order moved to processing.');
    }

    public function rejectPayment(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'payment_note' => ['required', 'string', 'max:1000'],
        ], [
            'payment_note.required' => 'Please give the customer a reason for the rejection.',
        ]);

        $order->update([
            'payment_status' => 'rejected',
            'payment_verified_at' => null,
            'payment_note' => $data['payment_note'],
        ]);

        return back()->with('success', 'Payment rejected — the customer can resubmit a screenshot.');
    }
}
