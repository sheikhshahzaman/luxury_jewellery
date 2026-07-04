<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterController extends Controller
{
    public function index(): View
    {
        return view('admin.newsletter', [
            'subscribers' => NewsletterSubscriber::latest()->paginate(25),
            'total' => NewsletterSubscriber::count(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $filename = 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'subscribed_at']);
            NewsletterSubscriber::orderBy('id')->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $sub) {
                    fputcsv($out, [$sub->email, $sub->created_at?->toDateTimeString()]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed.');
    }
}
