<?php

namespace App\Http\Controllers;

use App\Services\LiveRates;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class RatesController extends Controller
{
    public function __construct(protected LiveRates $rates)
    {
    }

    public function index(): View
    {
        return view('pages.rates', ['snapshot' => $this->rates->snapshot()]);
    }

    /**
     * JSON for the rates page and the header ticker: spot rates and the
     * store's own prices, kept separate, straight from the database.
     */
    public function live(): JsonResponse
    {
        return response()->json($this->rates->snapshot())
            ->header('Cache-Control', 'no-store');
    }
}
