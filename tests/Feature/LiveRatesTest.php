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
        config(['rates.retry_backoff_ms' => [0], 'rates.provider' => 'gold_api', 'rates.fx_provider' => 'open_er_api']);
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
