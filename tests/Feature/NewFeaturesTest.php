<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $name, float $price, bool $featured = false): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'rings'], ['name' => 'Rings']);

        return Product::create([
            'category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug(),
            'price' => $price, 'stock' => 5, 'is_active' => true, 'is_featured' => $featured,
        ]);
    }

    // ---------- Search suggestions ----------

    public function test_search_suggest_returns_matches(): void
    {
        $this->product('Aurelia Band', 800);
        $this->product('Solene Ring', 900);

        $this->getJson('/search/suggest?q=a')->assertOk()->assertJson(['results' => []]); // < 2 chars

        $res = $this->getJson('/search/suggest?q=aur')->assertOk()->json('results');
        $this->assertCount(1, $res);
        $this->assertSame('Aurelia Band', $res[0]['name']);
        $this->assertArrayHasKey('url', $res[0]);
        $this->assertArrayHasKey('price', $res[0]);
        $this->assertArrayHasKey('image', $res[0]);
    }

    public function test_search_suggest_excludes_inactive(): void
    {
        $p = $this->product('Hidden Gem', 500);
        $p->update(['is_active' => false]);

        $this->getJson('/search/suggest?q=hidden')->assertOk()->assertJsonCount(0, 'results');
    }

    // ---------- Offers page ----------

    public function test_offers_page_lists_only_public_valid_coupons(): void
    {
        Coupon::create(['code' => 'PUBLIC10', 'type' => 'percent', 'value' => 10, 'is_active' => true, 'is_public' => true]);
        Coupon::create(['code' => 'PRIVATEX', 'type' => 'percent', 'value' => 20, 'is_active' => true, 'is_public' => false]);
        Coupon::create(['code' => 'EXPIRED', 'type' => 'percent', 'value' => 30, 'is_active' => true, 'is_public' => true, 'expires_at' => now()->subDay()]);
        Coupon::create(['code' => 'INACTIVE', 'type' => 'percent', 'value' => 40, 'is_active' => false, 'is_public' => true]);

        $this->get('/offers')->assertOk()
            ->assertSee('PUBLIC10')
            ->assertDontSee('PRIVATEX')
            ->assertDontSee('EXPIRED')
            ->assertDontSee('INACTIVE');
    }

    public function test_offers_linked_in_nav(): void
    {
        $this->get('/')->assertSee(route('page.offers'));
    }

    // ---------- Contact map ----------

    public function test_contact_shows_map_from_address(): void
    {
        Setting::set('contact_address', 'Lisbon, Portugal');
        Setting::flushCache();

        $this->get('/contact')->assertOk()->assertSee('maps.google.com/maps', false);
    }

    public function test_contact_map_prefers_coordinates_openstreetmap(): void
    {
        Setting::set('contact_address', 'Lisbon, Portugal');
        Setting::set('map_latitude', '38.7071');
        Setting::set('map_longitude', '-9.1355');
        Setting::flushCache();

        $this->get('/contact')->assertOk()
            ->assertSee('openstreetmap.org/export/embed', false)
            ->assertDontSee('maps.google.com/maps', false);
    }

    public function test_contact_map_embed_url_overrides(): void
    {
        Setting::set('map_latitude', '38.7071');
        Setting::set('map_longitude', '-9.1355');
        Setting::set('map_embed_url', 'https://www.google.com/maps/embed?pb=customEmbed');
        Setting::flushCache();

        $this->get('/contact')->assertOk()->assertSee('maps/embed?pb=customEmbed', false);
    }

    // ---------- Wishlist on cards ----------

    public function test_product_card_shows_wishlist_control(): void
    {
        $this->product('Aurelia Band', 800, true);

        // Guest: heart links to login.
        $this->get('/shop')->assertOk()->assertSee('card-wish');

        // Auth: heart is a toggle form.
        $user = User::create(['name' => 'C', 'email' => 'c@t.com', 'password' => 'password']);
        $this->actingAs($user)->get('/shop')->assertOk()->assertSee(route('wishlist.toggle', Product::first()));
    }

    // ---------- Admin editability ----------

    public function test_admin_uploads_logo_and_it_shows(): void
    {
        Storage::fake('public');
        $admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'password', 'role' => 'admin']);

        $this->actingAs($admin)->post('/admin/settings', [
            'store_name' => 'Eluxee',
            'logo_file' => UploadedFile::fake()->image('logo.png', 200, 60),
        ])->assertRedirect();

        Setting::flushCache();
        $logo = Setting::get('logo');
        $this->assertNotEmpty($logo);
        Storage::disk('public')->assertExists($logo);

        // The header now renders an <img> logo instead of the text name.
        $this->get('/')->assertSee('brand-logo');
    }

    public function test_admin_edits_value_props_and_headings(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'password', 'role' => 'admin']);
        $this->product('X', 100, true); // so the home page has content

        $this->actingAs($admin)->post('/admin/settings', [
            'store_name' => 'Eluxee',
            'value_1_title' => 'Conflict-free diamonds',
            'bestsellers_title' => 'Our favourites',
            'newsletter_title' => 'Join our list',
        ])->assertRedirect();

        Setting::flushCache();
        $this->get('/')
            ->assertSee('Conflict-free diamonds')
            ->assertSee('Our favourites')
            ->assertSee('Join our list');
    }
}
