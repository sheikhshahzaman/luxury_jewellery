<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function offers(): View
    {
        return view('pages.offers', [
            'coupons' => Coupon::publiclyListable()->orderByDesc('value')->get(),
        ]);
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function submitContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        ContactMessage::create($data);

        return back()->with('success', 'Thank you — your message has been sent. We reply within one business day.');
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        NewsletterSubscriber::firstOrCreate(['email' => strtolower($request->input('email'))]);

        return back()->with('success', 'Welcome to the circle — you are subscribed.');
    }
}
