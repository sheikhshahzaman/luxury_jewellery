<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected Category $category;

    protected Product $product;

    protected BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'password', 'role' => 'admin',
        ]);
        $this->customer = User::create([
            'name' => 'Customer', 'email' => 'customer@test.com', 'password' => 'password', 'role' => 'customer',
        ]);
        $this->category = Category::create(['name' => 'Rings', 'slug' => 'rings']);
        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Ring', 'slug' => 'test-ring', 'sku' => 'TR-1',
            'price' => 500, 'sale_price' => 400, 'stock' => 10,
            'is_featured' => true, 'is_active' => true,
        ]);
        $this->bank = BankAccount::create([
            'bank_name' => 'Test Bank', 'account_name' => 'Store', 'account_number' => '123456',
        ]);
    }

    // ------------------------------------------------------------------
    // Public pages
    // ------------------------------------------------------------------

    public function test_public_pages_render(): void
    {
        Testimonial::create(['name' => 'A', 'quote' => 'Lovely.', 'rating' => 5]);

        $this->get('/')->assertOk()->assertSee('Test Ring');
        $this->get('/shop')->assertOk()->assertSee('Test Ring');
        $this->get('/shop?category=rings&sort=price_asc&min=100&max=900&q=ring')->assertOk();
        $this->get('/product/test-ring')->assertOk()->assertSee('Test Ring')->assertSee('Save 20%');
        $this->get('/about')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/cart')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_inactive_product_hidden(): void
    {
        $this->product->update(['is_active' => false]);
        $this->get('/product/test-ring')->assertNotFound();
        $this->get('/shop')->assertDontSee('Test Ring');
    }

    public function test_contact_and_newsletter(): void
    {
        $this->post('/contact', [
            'name' => 'Jo', 'email' => 'jo@test.com', 'message' => 'Hello there',
        ])->assertRedirect();
        $this->assertDatabaseHas('contact_messages', ['email' => 'jo@test.com']);

        $this->post('/newsletter', ['email' => 'sub@test.com'])->assertRedirect();
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'sub@test.com']);
    }

    // ------------------------------------------------------------------
    // Auth
    // ------------------------------------------------------------------

    public function test_registration_and_login(): void
    {
        $this->post('/register', [
            'name' => 'New User', 'email' => 'new@test.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('account.dashboard'));

        $this->post('/logout');

        $this->post('/login', ['email' => 'new@test.com', 'password' => 'password123'])
            ->assertRedirect(route('account.dashboard'));
    }

    public function test_blocked_user_cannot_login(): void
    {
        $this->customer->update(['is_blocked' => true]);

        $this->post('/login', ['email' => 'customer@test.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_redirects_to_admin_panel_on_login(): void
    {
        $this->post('/login', ['email' => 'admin@test.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    // ------------------------------------------------------------------
    // Cart & coupons
    // ------------------------------------------------------------------

    public function test_cart_add_update_remove(): void
    {
        $this->post('/cart/add/'.$this->product->slug, ['qty' => 2])->assertRedirect();
        $this->get('/cart')->assertSee('Test Ring');

        $this->post('/cart/update/'.$this->product->slug, ['qty' => 3])->assertRedirect();
        $this->post('/cart/remove/'.$this->product->slug)->assertRedirect();
        $this->get('/cart')->assertSee('Your cart is empty');
    }

    public function test_cart_respects_stock_limit(): void
    {
        $this->product->update(['stock' => 2]);
        $this->post('/cart/add/'.$this->product->slug, ['qty' => 5]);

        $response = $this->get('/cart');
        $response->assertSee('Test Ring');
        // 2 in stock * 400 sale price = 800 max line total
        $response->assertSee('800');
    }

    public function test_coupon_apply_and_validation(): void
    {
        Coupon::create(['code' => 'TEN', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        Coupon::create(['code' => 'BIG', 'type' => 'fixed', 'value' => 50, 'min_order' => 5000, 'is_active' => true]);

        $this->post('/cart/add/'.$this->product->slug, ['qty' => 1]);

        $this->post('/coupon/apply', ['code' => 'nope'])->assertSessionHas('error');
        $this->post('/coupon/apply', ['code' => 'BIG'])->assertSessionHas('error'); // min order not met
        $this->post('/coupon/apply', ['code' => 'ten'])->assertSessionHas('success');

        $this->get('/cart')->assertSee('TEN');
    }

    // ------------------------------------------------------------------
    // Checkout — bank transfer + screenshot
    // ------------------------------------------------------------------

    public function test_checkout_requires_auth(): void
    {
        $this->post('/cart/add/'.$this->product->slug, ['qty' => 1]);
        $this->get('/checkout')->assertRedirect(route('login'));
    }

    public function test_checkout_shows_bank_accounts(): void
    {
        $this->actingAs($this->customer);
        $this->post('/cart/add/'.$this->product->slug, ['qty' => 1]);

        $this->get('/checkout')->assertOk()
            ->assertSee('Test Bank')
            ->assertSee('123456')
            ->assertSee('Upload payment screenshot');
    }

    public function test_checkout_requires_screenshot(): void
    {
        $this->actingAs($this->customer);
        $this->post('/cart/add/'.$this->product->slug, ['qty' => 1]);

        $this->post('/checkout', $this->shippingData())->assertSessionHasErrors('payment_screenshot');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_full_checkout_flow(): void
    {
        Storage::fake('local');
        $this->actingAs($this->customer);
        $this->post('/cart/add/'.$this->product->slug, ['qty' => 2]);

        $response = $this->post('/checkout', $this->shippingData() + [
            'payment_screenshot' => UploadedFile::fake()->image('receipt.png', 600, 400),
            'payment_reference' => 'TRX-1',
            'save_address' => 1,
        ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('checkout.confirmation', $order));

        $this->assertStringStartsWith('ELX-', $order->order_number);
        $this->assertSame('pending', $order->status);
        $this->assertSame('submitted', $order->payment_status);
        $this->assertSame($this->bank->id, $order->bank_account_id);
        $this->assertSame(800.0, (float) $order->subtotal); // 2 × 400 sale price
        $this->assertSame(0.0, (float) $order->shipping_fee); // free over default 150
        Storage::disk('local')->assertExists($order->payment_screenshot);

        $this->assertSame(8, $this->product->fresh()->stock); // stock decremented
        $this->assertDatabaseHas('addresses', ['user_id' => $this->customer->id]); // saved address
        $this->assertSame(0, cart_count()); // cart cleared

        // Confirmation + account pages
        $this->get(route('checkout.confirmation', $order))->assertOk()->assertSee($order->order_number);
        $this->get('/account/orders')->assertOk()->assertSee($order->order_number);
        $this->get('/account/orders/'.$order->id)->assertOk()->assertSee('Awaiting verification');

        // Screenshot access: owner yes, other customer no, admin yes.
        $this->get(route('orders.screenshot', $order))->assertOk();
        $other = User::create(['name' => 'X', 'email' => 'x@test.com', 'password' => 'password']);
        $this->actingAs($other)->get(route('orders.screenshot', $order))->assertForbidden();
        $this->actingAs($other)->get('/account/orders/'.$order->id)->assertForbidden();
        $this->actingAs($this->admin)->get(route('orders.screenshot', $order))->assertOk();
    }

    public function test_checkout_blocks_overselling(): void
    {
        Storage::fake('local');
        $this->actingAs($this->customer);
        $this->post('/cart/add/'.$this->product->slug, ['qty' => 2]);

        // Product sells out entirely after being added to the cart.
        $this->product->update(['stock' => 0]);

        $this->post('/checkout', $this->shippingData() + [
            'payment_screenshot' => UploadedFile::fake()->image('receipt.png'),
        ])->assertRedirect(route('cart.index'));

        $this->assertDatabaseCount('orders', 0);

        // With stock reduced (not sold out), the cart clamps to what is available.
        $this->product->update(['stock' => 1]);
        $this->post('/cart/add/'.$this->product->slug, ['qty' => 1]);
        $this->post('/checkout', $this->shippingData() + [
            'payment_screenshot' => UploadedFile::fake()->image('receipt.png'),
        ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame(1, $order->items()->first()->quantity);
        $this->assertSame(0, $this->product->fresh()->stock);
    }

    // ------------------------------------------------------------------
    // Payment verification lifecycle
    // ------------------------------------------------------------------

    public function test_admin_verifies_payment(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->admin);
        $this->get('/admin/orders')->assertOk()->assertSee($order->order_number);
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Payment verification');

        $this->post('/admin/orders/'.$order->id.'/verify-payment')->assertRedirect();

        $order->refresh();
        $this->assertSame('verified', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertNotNull($order->payment_verified_at);
    }

    public function test_admin_rejects_payment_and_customer_resubmits(): void
    {
        Storage::fake('local');
        $order = $this->placeOrder();

        // Reject requires a note.
        $this->actingAs($this->admin);
        $this->post('/admin/orders/'.$order->id.'/reject-payment', [])->assertSessionHasErrors('payment_note');
        $this->post('/admin/orders/'.$order->id.'/reject-payment', ['payment_note' => 'Amount mismatch'])->assertRedirect();

        $order->refresh();
        $this->assertSame('rejected', $order->payment_status);

        // Customer sees the rejection and resubmits.
        $this->actingAs($this->customer);
        $this->get('/account/orders/'.$order->id)->assertSee('Amount mismatch');
        $this->post('/account/orders/'.$order->id.'/payment', [
            'payment_screenshot' => UploadedFile::fake()->image('new-receipt.png'),
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('submitted', $order->payment_status);
        $this->assertNull($order->payment_note);
    }

    public function test_order_status_flow_and_cancel_restores_stock(): void
    {
        $order = $this->placeOrder(qty: 2); // stock 10 -> 8

        $this->actingAs($this->admin);
        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'shipped', 'tracking_number' => 'TRACK9'])
            ->assertRedirect();
        $order->refresh();
        $this->assertSame('shipped', $order->status);
        $this->assertNotNull($order->shipped_at);
        $this->assertSame('TRACK9', $order->tracking_number);

        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_customer_cancels_pending_order(): void
    {
        $order = $this->placeOrder(qty: 1); // stock -> 9

        $this->actingAs($this->customer);
        $this->post('/account/orders/'.$order->id.'/cancel')->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);

        // Cannot cancel twice / when not pending.
        $this->post('/account/orders/'.$order->id.'/cancel')->assertSessionHas('error');
    }

    // ------------------------------------------------------------------
    // Reviews & wishlist
    // ------------------------------------------------------------------

    public function test_only_buyers_can_review_and_admin_approves(): void
    {
        $this->actingAs($this->customer);

        // Not a buyer yet.
        $this->post('/product/test-ring/review', ['rating' => 5, 'comment' => 'Nice'])
            ->assertSessionHas('error');

        $this->placeOrder();

        $this->post('/product/test-ring/review', ['rating' => 5, 'comment' => 'Stunning piece'])
            ->assertSessionHas('success');

        // Not yet visible (unapproved).
        $this->assertSame(0, $this->product->fresh()->reviews_count);
        $this->get('/product/test-ring')->assertDontSee('Stunning piece');

        // Admin approves.
        $review = $this->product->reviews()->first();
        $this->actingAs($this->admin)->post('/admin/reviews/'.$review->id.'/approve');

        $fresh = $this->product->fresh();
        $this->assertSame(1, $fresh->reviews_count);
        $this->assertEquals(5.0, (float) $fresh->rating_avg);
        $this->get('/product/test-ring')->assertSee('Stunning piece');

        // Duplicate review rejected.
        $this->actingAs($this->customer)
            ->post('/product/test-ring/review', ['rating' => 1])
            ->assertSessionHas('error');
    }

    public function test_wishlist_toggle(): void
    {
        $this->actingAs($this->customer);

        $this->post('/wishlist/'.$this->product->slug)->assertRedirect();
        $this->get('/account/wishlist')->assertSee('Test Ring');

        $this->post('/wishlist/'.$this->product->slug);
        $this->get('/account/wishlist')->assertSee('Your wishlist is empty');
    }

    // ------------------------------------------------------------------
    // Admin panel
    // ------------------------------------------------------------------

    public function test_admin_area_blocked_for_customers_and_guests(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs($this->customer)->get('/admin')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin')->assertOk();
    }

    public function test_admin_pages_render(): void
    {
        $this->placeOrder();
        $this->actingAs($this->admin);

        foreach (['/admin', '/admin/products', '/admin/products/create', '/admin/categories',
            '/admin/categories/create', '/admin/orders', '/admin/bank-accounts',
            '/admin/bank-accounts/create', '/admin/coupons', '/admin/coupons/create',
            '/admin/customers', '/admin/reviews', '/admin/testimonials',
            '/admin/testimonials/create', '/admin/settings', '/admin/newsletter', '/admin/messages'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_admin_creates_and_updates_product(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin);

        $this->post('/admin/products', [
            'name' => 'Gold Cuff', 'category_id' => $this->category->id,
            'price' => 900, 'sale_price' => 800, 'stock' => 5,
            'is_active' => 1, 'is_featured' => 1, 'tag' => 'New',
            'image_file' => UploadedFile::fake()->image('cuff.jpg'),
            'gallery' => [UploadedFile::fake()->image('g1.jpg'), UploadedFile::fake()->image('g2.jpg')],
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::where('name', 'Gold Cuff')->first();
        $this->assertNotNull($product);
        $this->assertSame('gold-cuff', $product->slug);
        $this->assertCount(2, $product->images);
        Storage::disk('public')->assertExists($product->image);

        // Duplicate name gets unique slug.
        $this->post('/admin/products', ['name' => 'Gold Cuff', 'price' => 100, 'stock' => 1, 'is_active' => 1]);
        $this->assertNotNull(Product::where('slug', 'gold-cuff-2')->first());

        // Update (product routes bind by slug).
        $this->put('/admin/products/'.$product->slug, [
            'name' => 'Gold Cuff', 'price' => 950, 'stock' => 6, 'is_active' => 1,
        ])->assertRedirect(route('admin.products.index'));
        $this->assertEquals(950.0, (float) $product->fresh()->price);
        $this->assertNull($product->fresh()->sale_price);
    }

    public function test_admin_manages_bank_accounts_and_checkout_reflects_it(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/bank-accounts', [
            'bank_name' => 'New Bank', 'account_name' => 'Eluxee', 'account_number' => '999888',
            'is_active' => 1,
        ])->assertRedirect(route('admin.bank-accounts.index'));

        // Deactivate the first bank; checkout should only offer the new one.
        $this->put('/admin/bank-accounts/'.$this->bank->id, [
            'bank_name' => 'Test Bank', 'account_name' => 'Store', 'account_number' => '123456',
        ]);

        $this->actingAs($this->customer);
        $this->post('/cart/add/'.$this->product->slug, ['qty' => 1]);
        $this->get('/checkout')->assertSee('New Bank')->assertDontSee('Test Bank');
    }

    public function test_admin_blocks_customer(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/customers/'.$this->customer->id.'/toggle-block')->assertRedirect();
        $this->assertTrue($this->customer->fresh()->is_blocked);
    }

    public function test_admin_saves_settings(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/settings', [
            'store_name' => 'Legacy Jewels',
            'shipping_fee' => '25',
            'announcement' => 'Grand opening',
        ])->assertRedirect();

        Setting::flushCache();
        $this->assertSame('Legacy Jewels', setting('store_name'));

        $this->get('/')->assertSee('Legacy Jewels')->assertSee('Grand opening');
    }

    public function test_admin_coupon_and_testimonial_crud(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/coupons', [
            'code' => 'summer20', 'type' => 'percent', 'value' => 20, 'is_active' => 1,
        ])->assertRedirect(route('admin.coupons.index'));
        $this->assertDatabaseHas('coupons', ['code' => 'SUMMER20']);

        $this->post('/admin/testimonials', [
            'name' => 'Zara', 'quote' => 'Beautiful work.', 'rating' => 5, 'is_active' => 1,
        ])->assertRedirect(route('admin.testimonials.index'));
        $this->get('/')->assertSee('Beautiful work.');
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin);
        $this->delete('/admin/categories/'.$this->category->slug)->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $this->category->id]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function shippingData(): array
    {
        return [
            'ship_name' => 'Customer', 'ship_phone' => '0300', 'ship_address' => 'Street 1',
            'ship_city' => 'Lahore', 'ship_country' => 'Pakistan',
            'bank_account_id' => $this->bank->id,
        ];
    }

    protected function placeOrder(int $qty = 1): Order
    {
        Storage::fake('local');
        $this->actingAs($this->customer);
        $this->post('/cart/add/'.$this->product->slug, ['qty' => $qty]);
        $this->post('/checkout', $this->shippingData() + [
            'payment_screenshot' => UploadedFile::fake()->image('receipt.png'),
        ]);

        return Order::latest('id')->first();
    }
}
