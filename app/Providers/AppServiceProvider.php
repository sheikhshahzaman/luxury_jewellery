<?php

namespace App\Providers;

use App\Models\Category;
use App\Services\LiveRates;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Active categories for the header menu and footer, loaded once per request.
        View::composer(['partials.navbar', 'partials.footer'], function ($view) {
            $attributes = request()->attributes;

            if (! $attributes->has('navCategories')) {
                $attributes->set('navCategories', Category::active()->ordered()
                    ->withCount(['products as count' => fn ($q) => $q->where('is_active', true)])
                    ->get());
            }

            $view->with('navCategories', $attributes->get('navCategories'));
        });

        // Live 1-tola gold and silver rates for the announcement-bar ticker.
        View::composer('partials.announcement', function ($view) {
            try {
                $rates = app(LiveRates::class);
                $ticker = array_filter(['gold' => $rates->headline('gold'), 'silver' => $rates->headline('silver')]);
            } catch (\Throwable) {
                return; // e.g. before the rates table is migrated
            }

            $view->with(['tickerRates' => $ticker, 'tickerNextIn' => (int) config('rates.poll_seconds', 30) * 1000]);
        });
    }
}
