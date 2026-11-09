<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use App\Services\OrderWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(): View
    {
        $user = auth()->user();

        return view('account.dashboard', [
            'user' => $user,
            'ordersCount' => $user->orders()->count(),
            'pendingCount' => $user->orders()->whereIn('status', ['pending', 'processing', 'shipped'])->count(),
            'wishlistCount' => $user->wishlist()->count(),
            'recentOrders' => $user->orders()->latest()->take(5)->get(),
        ]);
    }

    public function orders(): View
    {
        return view('account.orders', [
            'orders' => auth()->user()->orders()->latest()->paginate(10),
        ]);
    }

    public function showOrder(Order $order): View
    {
        $this->authorizeOrder($order);
        $order->load(['items.product', 'bankAccount']);

        return view('account.order-show', compact('order'));
    }

    public function resubmitPayment(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($order);

        if (! $order->canResubmitPayment()) {
            return back()->with('error', 'Payment cannot be resubmitted for this order.');
        }

        $data = $request->validate([
            'payment_screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'payment_reference' => ['required', 'string', 'max:120'],
            'payer_name' => ['required', 'string', 'max:255'],
        ], [
            'payment_reference.required' => 'Please enter the transaction ID from your receipt.',
            'payer_name.required' => 'Please enter the name the payment was sent from.',
        ]);

        $old = $order->payment_screenshot;

        $order->update([
            'payment_screenshot' => $request->file('payment_screenshot')->store('payments'),
            'payment_reference' => $data['payment_reference'],
            'payer_name' => $data['payer_name'],
            'payment_status' => 'submitted',
            'payment_note' => null,
        ]);

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', 'New payment screenshot submitted — we will verify it shortly.');
    }

    public function cancelOrder(Order $order, OrderWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOrder($order);

        try {
            $workflow->cancelByCustomer($order);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order '.$order->order_number.' has been cancelled.');
    }

    // ------------------------------------------------------------------
    // Profile
    // ------------------------------------------------------------------

    public function profile(): View
    {
        return view('account.profile', ['user' => auth()->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        auth()->user()->update(['password' => Hash::make($request->input('password'))]);

        return back()->with('success', 'Password changed.');
    }

    // ------------------------------------------------------------------
    // Addresses
    // ------------------------------------------------------------------

    public function addresses(): View
    {
        return view('account.addresses', [
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->latest()->get(),
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $this->validateAddress($request);

        $address = auth()->user()->addresses()->create($data);

        if ($request->boolean('is_default')) {
            $this->makeDefault($address);
        }

        return back()->with('success', 'Address saved.');
    }

    public function updateAddress(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === auth()->id(), 403);

        $address->update($this->validateAddress($request));

        if ($request->boolean('is_default')) {
            $this->makeDefault($address);
        }

        return back()->with('success', 'Address updated.');
    }

    public function deleteAddress(Address $address): RedirectResponse
    {
        abort_unless($address->user_id === auth()->id(), 403);
        $address->delete();

        return back()->with('success', 'Address removed.');
    }

    public function defaultAddress(Address $address): RedirectResponse
    {
        abort_unless($address->user_id === auth()->id(), 403);
        $this->makeDefault($address);

        return back()->with('success', 'Default address updated.');
    }

    // ------------------------------------------------------------------
    // Wishlist
    // ------------------------------------------------------------------

    public function wishlist(): View
    {
        return view('account.wishlist', [
            'products' => auth()->user()->wishlist()->with('category')->latest('wishlists.created_at')->get(),
        ]);
    }

    // ------------------------------------------------------------------

    protected function authorizeOrder(Order $order): void
    {
        abort_unless($order->user_id === auth()->id(), 403);
    }

    protected function validateAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:120'],
        ]);
    }

    protected function makeDefault(Address $address): void
    {
        auth()->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }
}
