<?php

namespace Tests\Feature;

use App\Models\MetalRate;
use App\Models\MetalRateChange;
use App\Models\Setting;
use App\Models\SpotRate;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\SpotRates;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveRatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        Cache::flush();
        DatabaseSeeder::seedMetalRates();
        config(['rates.retry_backoff_ms' => [0], 'rates.providers' => ['gold_api'], 'rates.fx_provider' => 'open_er_api']);
    }

    /** What the fake provider answers; tests swap it mid-way. Null means "nothing faked yet". */
    protected ?\Closure $answer = null;

    /** One HTTP fake for the whole test, answering with whatever $answer returns. */
    protected function provider(\Closure $answer): void
    {
        if (! $this->answer) {
            Http::fake(fn ($request) => ($this->answer)($request));
        }
        $this->answer = $answer;
    }

    protected function admin(): User
    {
        return User::create(['name' => 'Rates Admin', 'email' => 'a@test.com', 'password' => 'password', 'role' => 'admin']);
    }

    protected function fakeProvider(float $gold = 4130.5, float $silver = 53.43, float $pkr = 276.77): void
    {
        $this->provider(fn ($request) => match (true) {
            str_ends_with($request->url(), '/XAU') => Http::response(['price' => $gold, 'symbol' => 'XAU']),
            str_ends_with($request->url(), '/XAG') => Http::response(['price' => $silver, 'symbol' => 'XAG']),
            default => Http::response(['result' => 'success', 'rates' => ['PKR' => $pkr]]),
        });
    }

    /** POST body for the prices form, with optional overrides by key. */
    protected function pricesForm(array $overrides = []): array
    {
        return ['rows' => MetalRate::all()->mapWithKeys(fn (MetalRate $r) => [$r->id => array_merge([
            'metal' => $r->metal, 'label' => $r->label, 'note' => $r->note, 'unit' => $r->unit,
            'quantity' => (float) $r->quantity, 'buy' => (float) $r->buy, 'sell' => (float) $r->sell,
            'sort_order' => $r->sort_order, 'is_active' => 1,
            'pricing' => $r->pricing ?? 'manual', 'buy_margin' => (float) $r->buy_margin, 'sell_margin' => (float) $r->sell_margin,
        ], $overrides[$r->key] ?? [])])->all(),
            'rates_shuffle' => '1', 'rates_shuffle_pkr_min' => '10', 'rates_shuffle_pkr_max' => '20', 'rates_shuffle_seconds' => '3',
        ];
    }

    public function test_rates_page_shows_spot_prices_and_both_boards(): void
    {
        $this->get('/rates')->assertOk()
            ->assertSee('Gold &amp; silver rates today', false)
            ->assertSee('XAU/USD')->assertSee('XAG/USD')
            ->assertSee('Gold — 24K')->assertSee('Silver (XAG)')
            ->assertSee('QR Packaging')->assertSee('1 KG')
            ->assertSee('data-row="silver_10_tola_qr"', false);
    }

    public function test_endpoint_keeps_spot_and_admin_prices_separate_with_stable_keys(): void
    {
        $this->fakeProvider();
        app(SpotRates::class)->fetch();

        $json = $this->getJson('/rates/live')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure([
                'spot' => ['gold' => ['bid_usd_oz', 'ask_usd_oz', 'pkr_per_gram', 'pkr_per_tola', 'updated_at'], 'silver', 'usd_pkr' => ['buy', 'sell'], 'updated_at'],
                'prices' => [['key', 'label', 'metal', 'unit', 'quantity', 'buy', 'sell', 'currency', 'updated_at']],
            ])->json();

        $this->assertSame(4130.5, $json['spot']['gold']['bid_usd_oz']);
        // Ounce → gram uses 31.1035 g; gram → tola 11.6638 g.
        $this->assertEquals(round(4130.5 / 31.1035 * 276.77), $json['spot']['gold']['pkr_per_gram']);
        $this->assertEquals(round(4130.5 / 31.1035 * 11.6638 * 276.77), $json['spot']['gold']['pkr_per_tola']);

        // Check 3: QR Packaging and 999 are separate items with their own keys and prices.
        $prices = collect($json['prices'])->keyBy('key');
        $this->assertSame('Silver — 10 Tola (QR Packaging)', $prices['silver_10_tola_qr']['label']);
        $this->assertSame('Silver — 10 Tola (999)', $prices['silver_10_tola_999']['label']);
        $this->assertNotEquals($prices['silver_10_tola_qr']['buy'], $prices['silver_10_tola_999']['buy']);
        $this->assertSame(10.0, (float) $prices['silver_10_tola_qr']['quantity']);
    }

    public function test_admin_price_edit_appears_at_once_on_the_page_and_endpoint_with_audit(): void
    {
        $admin = $this->admin();
        // Warm the cache first, so a stale cached response would show up (check 5).
        $this->getJson('/rates/live')->assertOk();
        $this->get('/rates')->assertSee('Rs 72,010');

        $this->actingAs($admin)->get('/admin/rates')->assertOk()->assertSee('silver_10_tola_qr');
        $this->post('/admin/rates', $this->pricesForm(['silver_10_tola_qr' => ['buy' => 73500, 'sell' => 69000]]))
            ->assertSessionHas('success');

        // Check 1: the new value is on the website and in its JSON straight away.
        $this->get('/rates')->assertSee('Rs 73,500')->assertDontSee('Rs 72,010');
        $row = collect($this->getJson('/rates/live')->json('prices'))->firstWhere('key', 'silver_10_tola_qr');
        $this->assertEquals(73500, $row['buy']);
        $this->assertEquals(69000, $row['sell']);
        $this->assertGreaterThan(0, $row['change']);

        // 999 silver is untouched.
        $this->assertEquals(70997, MetalRate::where('key', 'silver_10_tola_999')->value('buy'));

        // Audit: who changed what, from what — only for the row that changed.
        $this->assertSame(1, MetalRateChange::count());
        $change = MetalRateChange::first();
        $this->assertSame('silver_10_tola_qr', $change->key);
        $this->assertEquals(72010, $change->buy_before);
        $this->assertEquals(73500, $change->buy);
        $this->assertSame($admin->id, $change->user_id);
        $this->assertSame($admin->id, MetalRate::where('key', 'silver_10_tola_qr')->value('updated_by'));
    }

    public function test_spot_fetch_never_changes_admin_prices(): void
    {
        $before = MetalRate::orderBy('id')->get(['key', 'buy', 'sell', 'updated_at'])->toArray();

        $this->fakeProvider(gold: 5000, silver: 70);
        $this->artisan('rates:fetch')->assertSuccessful();

        // Check 2
        $this->assertSame($before, MetalRate::orderBy('id')->get(['key', 'buy', 'sell', 'updated_at'])->toArray());
        $this->assertSame(0, MetalRateChange::count());
        $this->assertSame(5000.0, SpotRate::for('XAU')->newest()->first()->bid);
    }

    public function test_auto_priced_item_follows_spot_and_the_dollar_rate(): void
    {
        MetalRate::where('key', 'gold_1_tola')->update(['pricing' => 'auto', 'buy_margin' => 2, 'sell_margin' => 1]);
        $this->fakeProvider(gold: 4000, silver: 50, pkr: 280);
        app(SpotRates::class)->fetch();

        $prices = collect($this->getJson('/rates/live')->json('prices'))->keyBy('key');
        $tola = 4000 / 31.1035 * 11.6638 * 280;
        $this->assertEquals(round($tola * 1.02), $prices['gold_1_tola']['buy']);
        $this->assertEquals(round($tola * 1.01), $prices['gold_1_tola']['sell']);
        $this->assertTrue($prices['gold_1_tola']['auto']);
        $this->assertSame($this->getJson('/rates/live')->json('spot.gold.updated_at'), $prices['gold_1_tola']['updated_at']);
        $this->assertArrayNotHasKey('buy_margin', $prices['gold_1_tola']); // margins stay private

        // Manual items keep exactly what the admin typed.
        $this->assertEquals(376389, $prices['gold_10_gram']['buy']);
        $this->assertFalse($prices['gold_10_gram']['auto']);

        // The market moves: the next fetch moves the price, on the page, the feed and the ticker.
        $this->travel(5)->minutes();
        $this->fakeProvider(gold: 3500, silver: 50, pkr: 280);
        app(SpotRates::class)->fetch();
        $expected = round(3500 / 31.1035 * 11.6638 * 280 * 1.02);
        $row = collect($this->getJson('/rates/live')->json('prices'))->firstWhere('key', 'gold_1_tola');
        $this->assertEquals($expected, $row['buy']);
        $this->assertLessThan(0, $row['change']);
        $this->get('/rates')->assertSee('Rs '.number_format($expected));
        $this->get('/')->assertSee('Rs '.number_format($expected));
    }

    public function test_auto_priced_item_uses_grams_for_every_unit(): void
    {
        MetalRate::where('metal', 'silver')->update(['pricing' => 'auto', 'buy_margin' => 0, 'sell_margin' => 0]);
        $this->fakeProvider(gold: 4000, silver: 50, pkr: 280);
        app(SpotRates::class)->fetch();

        $prices = collect($this->getJson('/rates/live')->json('prices'))->keyBy('key');
        $gram = 50 / 31.1035 * 280;
        $this->assertEquals(round($gram * 1000), $prices['silver_1_kg']['buy']);
        $this->assertEquals(round($gram * 5 * 11.6638), $prices['silver_5_tola_bar']['sell']);
    }

    public function test_auto_priced_item_shows_the_saved_price_until_spot_and_dollar_rate_exist(): void
    {
        MetalRate::where('key', 'gold_1_tola')->update(['pricing' => 'auto', 'buy_margin' => 2, 'sell_margin' => 1]);

        $row = collect($this->getJson('/rates/live')->json('prices'))->firstWhere('key', 'gold_1_tola');
        $this->assertEquals(438993, $row['buy']);
        $this->assertEquals(434990, $row['sell']);
    }

    public function test_admin_switches_an_item_to_auto_with_margins(): void
    {
        $this->fakeProvider(gold: 4000, silver: 50, pkr: 280);
        app(SpotRates::class)->fetch();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/rates', $this->pricesForm([
            'gold_1_tola' => ['pricing' => 'auto', 'buy_margin' => 2.5, 'sell_margin' => 1.25],
        ]))->assertSessionHas('success');

        $rate = MetalRate::where('key', 'gold_1_tola')->first();
        $this->assertSame('auto', $rate->pricing);
        $this->assertEquals(2.5, $rate->buy_margin);
        $expected = round(4000 / 31.1035 * 11.6638 * 280 * 1.025);
        $this->assertEquals($expected, collect($this->getJson('/rates/live')->json('prices'))->firstWhere('key', 'gold_1_tola')['buy']);
        // The switch is in the audit trail with the price it gave; other rows are not.
        $this->assertSame(1, MetalRateChange::count());
        $this->assertEquals($expected, MetalRateChange::first()->buy);

        // Saving again without changes records nothing, even after the market has moved.
        $this->fakeProvider(gold: 3900, silver: 50, pkr: 280);
        app(SpotRates::class)->fetch();
        $this->post('/admin/rates', $this->pricesForm())->assertSessionHas('success');
        $this->assertSame(1, MetalRateChange::count());

        $this->get('/admin/rates')->assertOk()->assertSee('% over spot');

        // Margins outside a sensible range are refused; sell above buy is refused.
        $this->post('/admin/rates', $this->pricesForm(['gold_1_tola' => ['pricing' => 'auto', 'buy_margin' => 900, 'sell_margin' => 1]]))
            ->assertSessionHasErrors();
        $this->post('/admin/rates', $this->pricesForm(['gold_1_tola' => ['pricing' => 'auto', 'buy_margin' => 1, 'sell_margin' => 3]]))
            ->assertSessionHasErrors();

        // Back to manual: the typed price shows again.
        $this->post('/admin/rates', $this->pricesForm(['gold_1_tola' => ['pricing' => 'manual', 'buy' => 400000, 'sell' => 395000]]))
            ->assertSessionHas('success');
        $this->assertEquals(400000, collect($this->getJson('/rates/live')->json('prices'))->firstWhere('key', 'gold_1_tola')['buy']);
    }

    /** A Kitco quote line: symbol, currency, unit, time (New York), bid, mid, ask, change, change %, low, high. */
    protected function kitco(string $symbol, float $bid, float $ask, string $time = '2026-11-20 17:00:00'): string
    {
        return implode(',', [$symbol, 'USD', 'OUNCE', $time, $bid, round(($bid + $ask) / 2, 2), $ask, -86.3, -2.38, 3502, 3652.2])."\n";
    }

    public function test_spot_comes_from_kitco_and_shows_the_bid_like_kitco_does(): void
    {
        $this->travelTo('2026-11-22 15:30:00');
        SpotRate::query()->delete();
        config(['rates.providers' => ['kitco', 'gold_api']]);
        $this->provider(fn ($request) => match (true) {
            str_contains($request->url(), 'kitco') && str_contains($request->url(), 'symbol=AU') => Http::response($this->kitco('AU', 3537.90, 3539.90)),
            str_contains($request->url(), 'kitco') && str_contains($request->url(), 'symbol=AG') => Http::response($this->kitco('AG', 41.12, 41.37)),
            str_contains($request->url(), 'gold-api') => Http::response(['price' => 3539.0]),
            default => Http::response(['result' => 'success', 'rates' => ['PKR' => 280]]),
        });

        app(SpotRates::class)->fetch();

        $gold = $this->getJson('/rates/live')->json('spot.gold');
        $this->assertEquals(3537.90, $gold['bid_usd_oz']);
        $this->assertEquals(3539.90, $gold['ask_usd_oz']);
        // The headline spot price is the bid, as quoted by Kitco and TradingView.
        $this->assertEquals(3537.90, $gold['price']);
        $this->assertSame('kitco.com', $gold['source']);
        // Rupee figures on the spot card use the same price as the headline.
        $this->assertEquals(round(3537.90 / 31.1035 * 11.6638 * 280), $gold['pkr_per_tola']);
        $this->get('/rates')->assertSee('$3,537.90');
        $this->assertEquals(41.12, $this->getJson('/rates/live')->json('spot.silver.price'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'gold-api'));
    }

    public function test_gold_board_matches_the_sarafa_rate_at_todays_spot(): void
    {
        // 22 Nov 2026: Kitco bid $3,537.90, Rs 276.595853 per dollar, Karachi Sarafa 24K Rs 385,000 / 384,000 per tola.
        $this->travelTo('2026-11-22 15:30:00');
        SpotRate::query()->delete();
        config(['rates.providers' => ['kitco']]);
        $this->provider(fn ($request) => match (true) {
            str_contains($request->url(), 'symbol=AU') => Http::response($this->kitco('AU', 3537.90, 3539.90)),
            str_contains($request->url(), 'symbol=AG') => Http::response($this->kitco('AG', 41.12, 41.37)),
            default => Http::response(['result' => 'success', 'rates' => ['PKR' => 276.595853]]),
        });
        app(SpotRates::class)->fetch();
        MetalRate::where('metal', 'gold')->update(['pricing' => 'auto', 'buy_margin' => 4.915, 'sell_margin' => 4.643]);

        $prices = collect($this->getJson('/rates/live')->json('prices'))->keyBy('key');
        // Both sides are priced from the quoted spot (the bid); the margins make the spread.
        $this->assertEqualsWithDelta(385000, $prices['gold_1_tola']['buy'], 10);
        $this->assertEqualsWithDelta(384000, $prices['gold_1_tola']['sell'], 10);
        // Sarafa's 10 gram rate is the tola rate by weight: Rs 329,220.
        $this->assertEqualsWithDelta(329220, $prices['gold_10_gram']['sell'], 10);
    }

    public function test_spot_falls_back_to_the_next_provider_when_kitco_fails(): void
    {
        $this->travelTo('2026-11-22 15:30:00');
        SpotRate::query()->delete();
        config(['rates.providers' => ['kitco', 'gold_api']]);
        $broken = [
            fn () => Http::response('down', 503),
            fn () => Http::response(''),
            fn () => Http::response("<html>blocked</html>"),
            fn () => Http::response($this->kitco('AU', 0, 0)),
            fn () => Http::response($this->kitco('AU', 3539.90, 3537.90)),          // ask below bid
            fn () => Http::response($this->kitco('AU', 3537.90, 3539.90, '2026-11-10 17:00:00')), // stale
            fn () => Http::response($this->kitco('AG', 41.12, 41.37)),              // wrong metal
        ];
        foreach ($broken as $i => $answer) {
            $this->travel(5)->minutes();
            $this->provider(fn ($request) => match (true) {
                str_contains($request->url(), 'kitco') => $answer(),
                str_ends_with($request->url(), '/XAU') => Http::response(['price' => 3539.0 + $i]),
                str_ends_with($request->url(), '/XAG') => Http::response(['price' => 41.08]),
                default => Http::response(['result' => 'success', 'rates' => ['PKR' => 280]]),
            });

            $result = app(SpotRates::class)->fetch();

            $this->assertArrayHasKey('XAU', $result['saved'], "case $i");
            $gold = $this->getJson('/rates/live')->json('spot.gold');
            $this->assertEquals(3539.0 + $i, $gold['price'], "case $i");
            $this->assertSame('gold-api.com', $gold['source'], "case $i");
        }
    }

    public function test_spot_keeps_the_last_value_only_when_every_provider_fails(): void
    {
        config(['rates.providers' => ['kitco', 'gold_api']]);
        $this->provider(fn () => Http::response('down', 503));

        $result = app(SpotRates::class)->fetch();

        $this->assertArrayNotHasKey('XAU', $result['saved']);
        $this->assertStringContainsString('kitco', $result['failed']['XAU']);
        $this->assertStringContainsString('gold-api', $result['failed']['XAU']);
    }

    public function test_spot_is_fetched_every_minute_and_the_dollar_rate_hourly(): void
    {
        $this->assertSame(1, config('rates.fetch_every_minutes'));
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'rates:fetch'));
        $this->assertSame('* * * * *', preg_replace('#^\*/1 #', '* ', $event->expression));
        $this->get('/rates')->assertSee('updated every minute')->assertDontSee('every 1 minutes');

        $fx = 0;
        $this->provider(function ($request) use (&$fx) {
            if (str_contains($request->url(), 'er-api')) {
                $fx++;

                return Http::response(['result' => 'success', 'rates' => ['PKR' => 276.6]]);
            }

            return Http::response(['price' => str_ends_with($request->url(), '/XAU') ? 3537.9 : 41.12]);
        });
        $spot = app(SpotRates::class);

        $spot->fetch();
        $this->assertSame(1, $fx);
        // Gold and silver every minute; the dollar rate (which changes once a day) is not asked again for an hour.
        foreach (range(1, 59) as $minute) {
            $this->travel(1)->minutes();
            $result = $spot->fetch();
            $this->assertArrayHasKey('XAU', $result['saved']);
            $this->assertArrayNotHasKey('USDPKR', $result['failed']);
        }
        $this->assertSame(1, $fx);
        $this->assertSame(60, SpotRate::for('XAU')->where('source', 'gold-api.com')->count());
        $this->assertNotNull($this->getJson('/rates/live')->json('spot.usd_pkr'));

        $this->travel(2)->minutes();
        $spot->fetch();
        $this->assertSame(2, $fx);

        // "Fetch now" in Admin always asks for the dollar rate too.
        $this->actingAs($this->admin())->post('/admin/rates/fetch')->assertSessionHas('success');
        $this->assertSame(3, $fx);
    }

    public function test_provider_failure_keeps_the_last_good_values_and_time(): void
    {
        $this->fakeProvider();
        $spot = app(SpotRates::class);
        $spot->fetch();
        $good = $this->getJson('/rates/live')->json('spot');

        $this->travel(10)->minutes();

        // Check 4: the provider is down …
        $this->provider(fn () => throw new ConnectionException('timed out'));
        $result = $spot->fetch();
        $this->assertSame([], $result['saved']);
        $after = $this->getJson('/rates/live')->json('spot');
        $this->assertSame($good['gold']['price'], $after['gold']['price']);
        $this->assertSame($good['gold']['updated_at'], $after['gold']['updated_at']);
        $this->assertStringContainsString('XAU', setting('spot_last_error'));

        // … or answers with junk: zero, nonsense, or no price at all.
        foreach ([['price' => 0], ['price' => 9999999], ['error' => 'quota exceeded'], ['price' => 'n/a']] as $junk) {
            $this->provider(fn ($request) => str_contains($request->url(), 'gold-api') ? Http::response($junk) : Http::response(['result' => 'error']));
            $this->assertSame([], $spot->fetch()['saved']);
            $this->assertSame($good['gold']['price'], $this->getJson('/rates/live')->json('spot.gold.price'));
        }
        // A server error is retried, then given up on.
        $this->provider(fn () => Http::response('down', 503));
        $this->assertSame([], $spot->fetch()['saved']);
        $this->assertSame($good['silver']['updated_at'], $this->getJson('/rates/live')->json('spot.silver.updated_at'));
        $this->assertSame(3, SpotRate::where('source', '!=', 'manual')->count()); // only the first, good fetch was saved
    }

    public function test_manual_mode_saves_typed_spot_values_and_pauses_fetching(): void
    {
        $this->actingAs($this->admin())->post('/admin/rates/spot', [
            'spot_mode' => 'manual',
            'spot' => ['XAU' => ['bid' => 4100, 'ask' => 4110], 'XAG' => ['bid' => 52, 'ask' => 52.5], 'USDPKR' => ['bid' => 280, 'ask' => 281]],
        ])->assertSessionHas('success');

        $spot = $this->getJson('/rates/live')->json('spot');
        $this->assertSame('manual', $spot['mode']);
        $this->assertEquals(4100, $spot['gold']['bid_usd_oz']);
        $this->assertEquals(4110, $spot['gold']['ask_usd_oz']);
        $this->assertSame('manual', $spot['gold']['source']);

        // The schedule doesn't fetch, and "Fetch now" is refused, while on manual.
        $this->fakeProvider();
        $this->artisan('rates:fetch')->expectsOutputToContain('manual')->assertSuccessful();
        $this->post('/admin/rates/fetch')->assertSessionHas('error');
        Http::assertNothingSent();

        // Ask below bid is rejected.
        $this->post('/admin/rates/spot', ['spot_mode' => 'manual', 'spot' => ['XAU' => ['bid' => 4100, 'ask' => 4000], 'XAG' => ['bid' => 52, 'ask' => 53]]])
            ->assertSessionHasErrors('spot.XAU.ask');

        // Back to automatic.
        $this->post('/admin/rates/spot', ['spot_mode' => 'auto'])->assertSessionHas('success');
        $this->post('/admin/rates/fetch')->assertSessionHas('success');
        $this->assertSame('gold-api.com', $this->getJson('/rates/live')->json('spot.gold.source'));
    }

    public function test_price_validation_rejects_bad_values_and_unknown_items(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/rates', $this->pricesForm(['gold_1_tola' => ['buy' => -5]]))->assertSessionHasErrors();
        $this->post('/admin/rates', $this->pricesForm(['gold_1_tola' => ['sell' => 'abc']]))->assertSessionHasErrors();

        // A row id that isn't a configured item.
        $form = $this->pricesForm();
        $form['rows'][99999] = $form['rows'][array_key_first($form['rows'])];
        $this->post('/admin/rates', $form)->assertSessionHasErrors('rows.99999.label');

        // A new item can't reuse an existing key.
        $form = $this->pricesForm();
        $form['rows']['new-1'] = ['metal' => 'silver', 'key' => 'silver_10_tola_qr', 'label' => '10 Tola', 'unit' => 'tola', 'quantity' => 10, 'buy' => 1, 'sell' => 1, 'is_active' => 1];
        $this->post('/admin/rates', $form)->assertSessionHasErrors('rows.new-1.key');

        $this->assertSame(0, MetalRateChange::count());
        $this->assertEquals(438993, MetalRate::where('key', 'gold_1_tola')->value('buy'));
    }

    public function test_new_items_get_a_generated_key(): void
    {
        $form = $this->pricesForm();
        $form['rows']['new-1'] = ['metal' => 'gold', 'label' => '2 Tola', 'unit' => 'tola', 'quantity' => 2, 'buy' => 877000, 'sell' => 869000, 'is_active' => 1];

        $this->actingAs($this->admin())->post('/admin/rates', $form)->assertSessionHas('success');

        $this->assertDatabaseHas('metal_rates', ['key' => 'gold_2_tola', 'label' => '2 Tola']);
        $this->assertDatabaseHas('metal_rate_changes', ['key' => 'gold_2_tola', 'buy_before' => null]);
    }

    public function test_prune_keeps_recent_history_and_the_newest_reading(): void
    {
        config(['rates.keep_history_days' => 30]);
        SpotRate::query()->delete();
        SpotRate::create(['instrument' => 'XAU', 'bid' => 4000, 'ask' => 4000, 'source' => 'gold-api.com', 'fetched_at' => now()->subDays(60)]);
        SpotRate::create(['instrument' => 'XAU', 'bid' => 4100, 'ask' => 4100, 'source' => 'gold-api.com', 'fetched_at' => now()->subDay()]);
        SpotRate::create(['instrument' => 'XAG', 'bid' => 50, 'ask' => 50, 'source' => 'gold-api.com', 'fetched_at' => now()->subDays(90)]);

        $this->assertSame(1, app(SpotRates::class)->prune());
        $this->assertSame(1, SpotRate::for('XAU')->count());
        $this->assertSame(1, SpotRate::for('XAG')->count()); // the only XAG reading stays
    }

    public function test_endpoint_serves_exact_values_and_the_display_shuffle_settings(): void
    {
        $json = $this->getJson('/rates/live')->json();

        // Exact stored prices — the shuffle happens only in the browser.
        $this->assertEquals(438993, collect($json['prices'])->firstWhere('key', 'gold_1_tola')['buy']);
        $this->assertSame(['shuffle' => true, 'seconds' => 3, 'pkr' => [10, 20], 'cents' => ['gold' => [5, 45], 'silver' => [1, 5]]], $json['display']);
    }

    public function test_admin_controls_the_display_shuffle(): void
    {
        $this->actingAs($this->admin());

        $form = $this->pricesForm();
        $this->post('/admin/rates', ['rates_shuffle' => '0', 'rates_shuffle_pkr_min' => '15', 'rates_shuffle_pkr_max' => '40', 'rates_shuffle_seconds' => '5'] + $form)
            ->assertSessionHas('success');
        Setting::flushCache();
        $this->assertSame(['shuffle' => false, 'seconds' => 5, 'pkr' => [15, 40]], array_intersect_key(app(\App\Services\LiveRates::class)->display(), array_flip(['shuffle', 'seconds', 'pkr'])));
        // Prices themselves were not touched by saving these settings.
        $this->assertSame(0, MetalRateChange::count());

        $this->post('/admin/rates', ['rates_shuffle_pkr_min' => '30', 'rates_shuffle_pkr_max' => '10'] + $form)
            ->assertSessionHasErrors('rates_shuffle_pkr_max');
        $this->post('/admin/rates', ['rates_shuffle_seconds' => '1'] + $form)
            ->assertSessionHasErrors('rates_shuffle_seconds');
    }

    public function test_announcement_bar_shows_one_tola_ticker(): void
    {
        $this->get('/')->assertSee('data-ticker-row="gold_1_tola"', false)->assertSee('1 Tola');
    }

    public function test_testimonial_carousel_renders_every_active_testimonial(): void
    {
        foreach (range(1, 5) as $i) {
            Testimonial::create(['name' => "Person $i", 'quote' => "Quote number $i", 'rating' => 5, 'is_active' => true]);
        }

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(5, substr_count($html, 'data-tw-card'));
        $this->assertStringContainsString('Next testimonial', $html);
    }
}
