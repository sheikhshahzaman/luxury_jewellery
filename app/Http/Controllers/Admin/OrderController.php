<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderWorkflow;
use DomainException;
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
            ->when($request->query('method'), fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->query('q'), function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('order_number', 'like', "%{$term}%")
                        ->orWhere('ship_name', 'like', "%{$term}%")
                        ->orWhere('ship_phone', 'like', "%{$term}%")
                        ->orWhere('payment_reference', 'like', "%{$term}%")
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

    public function updateStatus(Request $request, Order $order, OrderWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Order::STATUSES)],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $order = $workflow->changeStatus($order, $data['status']);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Empty inputs clear the fields.
        $order->update([
            'tracking_number' => $data['tracking_number'] ?? null,
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        return back()->with('success', 'Order updated to "'.ucfirst($order->status).'".');
    }

    public function verifyPayment(Order $order, OrderWorkflow $workflow): RedirectResponse
    {
        try {
            $order = $workflow->verifyPayment($order);
        } catch (DomainException $e) {
            return back()->with('info', $e->getMessage());
        }

        return back()->with('success', $order->isCod()
            ? 'Cash marked as collected.'
            : 'Payment verified — order moved to '.$order->status.'.');
    }

    public function rejectPayment(Request $request, Order $order, OrderWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'payment_note' => ['required', 'string', 'max:1000'],
        ], [
            'payment_note.required' => 'Please give the customer a reason for the rejection.',
        ]);

        try {
            $workflow->rejectPayment($order, $data['payment_note']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment rejected — the customer can resubmit a screenshot.');
    }
}
