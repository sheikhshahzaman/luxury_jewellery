<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Manual payment methods (bank / JazzCash / Easypaisa / COD), jewellery
 * options, the order workflow rules, and regression tests for fixed bugs.
 */
class PaymentsAndOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected Category $rings;

    protected Product $plain;

    protected Product $ring;

    protected BankAccount $bank;

    protected BankAccount $jazz;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();
        Storage::fake('local');
        Storage::fake('public');

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'password', 'role' => 'admin']);
        $this->customer = User::create(['name' => 'Customer', 'email' => 'customer@test.com', 'password' => 'password', 'role' => 'customer']);
        $this->rings = Category::create(['name' => 'Rings', 'slug' => 'rings']);

        $this->plain = Product::create([
            'category_id' => $this->rings->id, 'name' => 'Plain Band', 'slug' => 'plain-band',
            'price' => 200, 'stock' => 10, 'is_active' => true,
        ]);
        $this->ring = Product::create([
            'category_id' => $this->rings->id, 'name' => 'Solitaire', 'slug' => 'solitaire',
            'price' => 1000, 'stock' => 3, 'is_active' => true,
            'metal_options' => [['name' => 'Yellow gold', 'price' => 0], ['name' => 'Rose gold', 'price' => 50]],
            'size_options' => ['6', '7'], 'size_label' => 'Ring size',
            'engraving_enabled' => true, 'engraving_fee' => 25,
        ]);

        $this->bank = BankAccount::create(['type' => 'bank', 'bank_name' => 'Meezan', 'account_name' => 'Store', 'account_number' => '111']);
        $this->jazz = BankAccount::create(['type' => 'jazzcash', 'bank_name' => 'JazzCash', 'account_name' => 'Store', 'account_number' => '0300-1234567']);
    }

    // ------------------------------------------------------------------
    // Payment methods
    // ------------------------------------------------------------------

    public function test_checkout_lists_each_configured_method(): void
    {
        $this->actingAs($this->customer)->post('/cart/add/plain-band');

        $this->get('/checkout')->assertOk()
            ->assertSee('Bank transfer')->assertSee('JazzCash')->assertSee('0300-1234567')
            ->assertSee('Cash on delivery')
            ->assertDontSee('Easypaisa'); // no Easypaisa account configured
    }

    public function test_cash_on_delivery_needs_no_receipt_and_adds_fee(): void
    {
        Setting::set('cod_fee', '10');
        $this->actingAs($this->customer)->post('/cart/add/plain-band');

        $this->post('/checkout', $this->shipping() + ['payment_method' => 'cod'])
            ->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNull($order->payment_screenshot);
        $this->assertNull($order->bank_account_id);
        $this->assertEquals(10.0, (float) $order->payment_fee);
        $this->assertEquals(210.0, (float) $order->total); // 200 + free shipping + 10 COD fee
        $this->get('/account/orders/'.$order->id)->assertSee('Pay on delivery');

        // COD orders can move forward without verification; delivery marks the cash collected.
        $this->actingAs($this->admin);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post('/admin/orders/'.$order->id.'/status', ['status' => $status])->assertSessionHas('success');
        }
        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('verified', $order->payment_status);
        $this->assertSame('Cash collected', $order->payment_status_label);
    }

    public function test_cod_can_be_switched_off(): void
    {
        Setting::set('cod_enabled', '0');
        $this->actingAs($this->customer)->post('/cart/add/plain-band');

        $this->get('/checkout')->assertDontSee('Pay in cash when it arrives');
        $this->post('/checkout', $this->shipping() + ['payment_method' => 'cod'])
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_wallet_payment_requires_transaction_id_sender_and_matching_account(): void
    {
        $this->actingAs($this->customer)->post('/cart/add/plain-band');

        $this->post('/checkout', $this->shipping() + ['payment_method' => 'jazzcash', 'bank_account_id' => $this->jazz->id])
            ->assertSessionHasErrors(['payment_reference', 'payer_name', 'payment_screenshot']);

        // A bank account can't be used to "pay by JazzCash".
        $this->post('/checkout', $this->shipping() + $this->proof() + ['payment_method' => 'jazzcash', 'bank_account_id' => $this->bank->id])
            ->assertSessionHasErrors('bank_account_id');
        $this->assertDatabaseCount('orders', 0);

        $this->post('/checkout', $this->shipping() + $this->proof() + ['payment_method' => 'jazzcash', 'bank_account_id' => $this->jazz->id])
            ->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('jazzcash', $order->payment_method);
        $this->assertSame('submitted', $order->payment_status);
        $this->assertSame('TX-998877', $order->payment_reference);
        $this->assertSame('Sara Khan', $order->payer_name);
        $this->assertSame('pending', $order->status);

        $this->get('/account/orders/'.$order->id)->assertSee('TX-998877')->assertSee('Sara Khan')->assertSee('JazzCash');
        $this->actingAs($this->admin)->get('/admin/orders/'.$order->id)->assertSee('TX-998877')->assertSee('Sara Khan');
    }

    // ------------------------------------------------------------------
    // Order workflow rules
    // ------------------------------------------------------------------

    public function test_unverified_orders_cannot_advance_and_delivered_is_final(): void
    {
        $order = $this->placeBankOrder();
        $this->actingAs($this->admin);

        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'processing'])->assertSessionHas('error');
        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'delivered'])->assertSessionHas('error');

        $this->post('/admin/orders/'.$order->id.'/verify-payment');
        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'shipped']);
        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'delivered']);
        $this->assertSame('delivered', $order->fresh()->status);

        // Delivered goods don't go back into stock.
        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])->assertSessionHas('error');
        $this->assertSame(9, $this->plain->fresh()->stock);
    }

    public function test_cancelling_twice_restores_stock_once_and_releases_coupon(): void
    {
        Coupon::create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 5, 'max_uses' => 1, 'is_active' => true]);
        $this->actingAs($this->customer)->post('/cart/add/plain-band', ['qty' => 2]);
        $this->post('/coupon/apply', ['code' => 'ONCE']);
        $this->post('/checkout', $this->shipping() + $this->proof() + ['payment_method' => 'bank', 'bank_account_id' => $this->bank->id]);
        $order = Order::firstOrFail();
        $this->assertSame(1, Coupon::first()->used_count);
        $this->assertSame(8, $this->plain->fresh()->stock);

        $this->actingAs($this->admin);
        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'cancelled']);
        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'cancelled']); // double submit

        $this->assertSame(10, $this->plain->fresh()->stock);
        $this->assertSame(0, Coupon::first()->used_count);

        // Reactivating takes stock and the coupon use again.
        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'pending'])->assertSessionHas('success');
        $this->assertSame(8, $this->plain->fresh()->stock);
        $this->assertSame(1, Coupon::first()->used_count);
    }

    public function test_cancelled_order_payment_cannot_be_verified(): void
    {
        $order = $this->placeBankOrder();
        $this->actingAs($this->customer)->post('/account/orders/'.$order->id.'/cancel');

        $this->actingAs($this->admin)->post('/admin/orders/'.$order->id.'/verify-payment')->assertSessionHas('info');
        $this->assertSame('submitted', $order->fresh()->payment_status);
    }

    public function test_coupon_usage_limit_is_rechecked_at_checkout(): void
    {
        $coupon = Coupon::create(['code' => 'LAST', 'type' => 'fixed', 'value' => 5, 'max_uses' => 1, 'is_active' => true]);
        $this->actingAs($this->customer)->post('/cart/add/plain-band');
        $this->post('/coupon/apply', ['code' => 'LAST'])->assertSessionHas('success');

        // Someone else uses the last redemption while this cart is open.
        $coupon->update(['used_count' => 1]);

        $this->post('/checkout', $this->shipping() + ['payment_method' => 'cod']);
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertSame(0.0, (float) (Order::first()?->discount ?? 0));
    }

    public function test_tracking_number_and_note_can_be_cleared(): void
    {
        $order = $this->placeBankOrder();
        $this->actingAs($this->admin);
        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'pending', 'tracking_number' => 'TRK1', 'admin_note' => 'call first']);
        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'pending', 'tracking_number' => '', 'admin_note' => '']);

        $order->refresh();
        $this->assertNull($order->tracking_number);
        $this->assertNull($order->admin_note);
    }

    // ------------------------------------------------------------------
    // Jewellery options
    // ------------------------------------------------------------------

    public function test_options_are_required_validated_and_priced(): void
    {
        // Quick add without choosing sends the shopper to the product page.
        $this->post('/cart/add/solitaire')->assertRedirect(route('product.show', $this->ring));
        $this->assertSame(0, cart_count());

        $this->post('/cart/add/solitaire', ['metal' => 'Platinum', 'size' => '6'])->assertSessionHasErrors('metal');
        $this->post('/cart/add/solitaire', ['metal' => 'Rose gold', 'size' => '12'])->assertSessionHasErrors('size');
        $this->post('/cart/add/solitaire', ['metal' => 'Rose gold', 'size' => '6', 'engraving' => str_repeat('x', 30)])
            ->assertSessionHasErrors('engraving');

        $this->post('/cart/add/solitaire', ['metal' => 'Rose gold', 'size' => '6', 'engraving' => 'A & J'])
            ->assertSessionHas('success');

        $this->get('/cart')->assertSee('Rose gold')->assertSee('Ring size 6')->assertSee('A &amp; J', false)
            ->assertSee('$1,075'); // 1000 + 50 rose gold + 25 engraving
    }

    public function test_option_lines_share_stock_and_reach_the_order(): void
    {
        $this->actingAs($this->customer);
        $this->post('/cart/add/solitaire', ['metal' => 'Yellow gold', 'size' => '6', 'qty' => 2]);
        $this->post('/cart/add/solitaire', ['metal' => 'Yellow gold', 'size' => '7', 'qty' => 5]); // only 1 left

        $this->assertSame(3, cart_count());

        $this->post('/checkout', $this->shipping() + ['payment_method' => 'cod'])->assertRedirect();

        $order = Order::with('items')->firstOrFail();
        $this->assertCount(2, $order->items);
        $this->assertSame(0, $this->ring->fresh()->stock);
        $sizes = $order->items->map(fn ($i) => $i->options['size'])->sort()->values()->all();
        $this->assertSame(['6', '7'], $sizes);
        $this->assertSame(['Yellow gold', 'Ring size 6'], $order->items->firstWhere('quantity', 2)->option_lines);

        $this->get('/account/orders/'.$order->id)->assertSee('Ring size 7');
        $this->actingAs($this->admin)->get('/admin/orders/'.$order->id)->assertSee('Ring size 7');
    }

    public function test_legacy_session_cart_still_works(): void
    {
        $this->withSession(['cart' => [$this->plain->id => 2]])
            ->get('/cart')->assertOk()->assertSee('Plain Band')->assertSee('$400');
    }

    public function test_admin_saves_product_options(): void
    {
        $this->actingAs($this->admin)->put('/admin/products/plain-band', [
            'name' => 'Plain Band', 'price' => 200, 'stock' => 10, 'is_active' => 1,
            'metal_options' => "Yellow gold | 0\nSilver | -40",
            'size_options' => '5, 6, 6, 7',
            'size_label' => 'Ring size',
            'engraving_enabled' => 1, 'engraving_fee' => 15,
        ])->assertRedirect(route('admin.products.index'));

        $p = $this->plain->fresh();
        $this->assertSame([['name' => 'Yellow gold', 'price' => 0.0], ['name' => 'Silver', 'price' => -40.0]], $p->metals());
        $this->assertSame(['5', '6', '7'], $p->sizes());
        $this->assertTrue($p->engraving_enabled);

        $this->put('/admin/products/plain-band', [
            'name' => 'Plain Band', 'price' => 200, 'stock' => 10, 'metal_options' => 'Gold | lots',
        ])->assertSessionHasErrors('metal_options');
    }

    // ------------------------------------------------------------------
    // Regression tests for fixed bugs
    // ------------------------------------------------------------------

    public function test_invalid_gallery_creates_no_product(): void
    {
        $this->actingAs($this->admin)->post('/admin/products', [
            'name' => 'Broken Upload', 'price' => 10, 'stock' => 1,
            'gallery' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')],
        ])->assertSessionHasErrors('gallery.0');

        $this->assertDatabaseMissing('products', ['name' => 'Broken Upload']);
    }

    public function test_duplicate_sku_is_a_validation_error(): void
    {
        $this->plain->update(['sku' => 'SKU-1']);
        $this->actingAs($this->admin)->post('/admin/products', [
            'name' => 'Other', 'sku' => 'SKU-1', 'price' => 10, 'stock' => 1,
        ])->assertSessionHasErrors('sku');
    }

    public function test_symbol_only_names_get_a_fallback_slug(): void
    {
        $this->actingAs($this->admin)->post('/admin/products', ['name' => '★★★', 'price' => 10, 'stock' => 1, 'is_active' => 1]);
        $product = Product::where('name', '★★★')->firstOrFail();
        $this->assertStringStartsWith('item-', $product->slug);
        $this->get('/admin/products')->assertOk();
    }

    public function test_coupon_codes_are_unique_regardless_of_case_and_last_all_day(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/coupons', ['code' => 'WELCOME', 'type' => 'percent', 'value' => 10, 'is_active' => 1]);
        $this->post('/admin/coupons', ['code' => 'welcome', 'type' => 'percent', 'value' => 10, 'is_active' => 1])
            ->assertSessionHasErrors('code');

        $this->post('/admin/coupons', ['code' => 'LASTDAY', 'type' => 'fixed', 'value' => 5, 'is_active' => 1, 'expires_at' => '2026-10-31']);
        $this->assertSame('23:59:59', Coupon::where('code', 'LASTDAY')->first()->expires_at->format('H:i:s'));
    }

    public function test_payment_account_used_by_orders_is_hidden_not_deleted(): void
    {
        $order = $this->placeBankOrder();

        $this->actingAs($this->admin)->delete('/admin/bank-accounts/'.$this->bank->id)->assertRedirect();
        $this->assertDatabaseHas('bank_accounts', ['id' => $this->bank->id, 'is_active' => false]);
        $this->assertSame($this->bank->id, $order->fresh()->bank_account_id);

        // An unused account is really deleted.
        $this->delete('/admin/bank-accounts/'.$this->jazz->id);
        $this->assertDatabaseMissing('bank_accounts', ['id' => $this->jazz->id]);
    }

    public function test_settings_validate_before_saving_and_sanitise_hero_title(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/settings', ['store_name' => 'Should Not Save', 'shipping_fee' => '-5'])
            ->assertSessionHasErrors('shipping_fee');
        $this->post('/admin/settings', ['logo_file' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')])
            ->assertSessionHasErrors('logo_file');
        Setting::flushCache();
        $this->assertNotSame('Should Not Save', setting('store_name'));

        $this->post('/admin/settings', ['hero_title' => 'Shine <em onclick="x()">bright</em><script>alert(1)</script>'])
            ->assertSessionHas('success');
        Setting::flushCache();
        $this->assertSame('Shine <em>bright</em>alert(1)', setting('hero_title'));
    }

    public function test_dashboard_chart_does_not_skip_short_months(): void
    {
        Carbon::setTestNow('2026-10-31 12:00:00');
        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertViewHas('monthly', fn ($m) => $m->pluck('label')->all() === ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct']);
        Carbon::setTestNow();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'customer@test.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'customer@test.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_search_suggest_returns_raw_names_for_safe_rendering(): void
    {
        $this->plain->update(['name' => '<img src=x onerror=alert(1)> Band']);

        // The JSON carries the raw name; eluxee.js escapes it before inserting into the page.
        $this->getJson('/search/suggest?q=Band')->assertOk()->assertJsonPath('results.0.name', '<img src=x onerror=alert(1)> Band');
        $this->assertStringContainsString('const esc =', file_get_contents(public_path('js/eluxee.js')));
    }

    public function test_delete_confirmations_survive_apostrophes(): void
    {
        $this->plain->update(['name' => "Mother's Day Locket"]);

        $html = $this->actingAs($this->admin)->get('/admin/products')->getContent();
        // The apostrophe is escaped inside the JS string, so the confirm() still runs.
        $this->assertStringContainsString("confirm('Delete “Mother\\u0027s Day Locket”?", $html);
    }

    // ------------------------------------------------------------------

    protected function shipping(): array
    {
        return [
            'ship_name' => 'Customer', 'ship_phone' => '0300', 'ship_address' => 'Street 1',
            'ship_city' => 'Lahore', 'ship_country' => 'Pakistan',
        ];
    }

    protected function proof(): array
    {
        return [
            'payment_reference' => 'TX-998877',
            'payer_name' => 'Sara Khan',
            'payment_screenshot' => UploadedFile::fake()->image('receipt.png'),
        ];
    }

    protected function placeBankOrder(): Order
    {
        $this->actingAs($this->customer)->post('/cart/add/plain-band');
        $this->post('/checkout', $this->shipping() + $this->proof() + ['payment_method' => 'bank', 'bank_account_id' => $this->bank->id]);

        return Order::latest('id')->firstOrFail();
    }
}
