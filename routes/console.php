<?php

use App\Services\SpotRates;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spot rates (gold, silver, USD/PKR). The store's own buy/sell prices are never touched here.
Artisan::command('rates:fetch {--force : Fetch even when Admin has spot rates on manual}', function (SpotRates $spot) {
    if ($spot->mode() === 'manual' && ! $this->option('force')) {
        $this->info('Spot rates are on manual in Admin; nothing fetched.');

        return 0;
    }

    ['saved' => $saved, 'failed' => $failed] = $spot->fetch();
    foreach ($saved as $instrument => $value) {
        $this->line("Saved $instrument: $value");
    }
    foreach ($failed as $instrument => $reason) {
        $this->warn("Kept the last $instrument value: $reason");
    }

    return $saved ? 0 : 1;
})->purpose('Fetch gold, silver and USD/PKR spot rates from the configured provider');

Artisan::command('rates:prune', function (SpotRates $spot) {
    $this->info($spot->prune().' old spot readings deleted.');
})->purpose('Delete spot-rate history older than config rates.keep_history_days');

$every = max(1, min(60, (int) config('rates.fetch_every_minutes', 5)));
Schedule::command('rates:fetch')->cron("*/{$every} * * * *")->withoutOverlapping(10);
Schedule::command('rates:prune')->dailyAt('03:30');
