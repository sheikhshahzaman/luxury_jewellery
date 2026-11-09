<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationAndVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
    }

    public function test_header_menu_lists_active_categories_with_counts(): void
    {
        $chain = Category::create(['name' => 'Chain', 'slug' => 'chain', 'sort_order' => 1]);
        Category::create(['name' => 'Gold piece', 'slug' => 'gold-piece', 'sort_order' => 2]);
        Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        Product::create(['category_id' => $chain->id, 'name' => 'Rope Chain', 'slug' => 'rope-chain', 'price' => 100, 'stock' => 3, 'is_active' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="categoryMenu"', $html);
        $this->assertStringContainsString(route('shop.index', ['category' => 'chain']), $html);
        $this->assertStringContainsString(route('shop.index', ['category' => 'gold-piece']), $html);
        $this->assertStringNotContainsString(route('shop.index', ['category' => 'hidden']), $html);

        // Home shows every active category; empty ones say so.
        $this->get('/')->assertSee('Coming soon');
        $this->get('/shop?category=chain')->assertOk()->assertSee('Rope Chain');
    }

    public function test_store_is_noindex_nofollow_by_default_and_can_be_opened_up(): void
    {
        $this->get('/')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        Setting::set('hide_from_search', '0');

        $response = $this->get('/');
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
        $response->assertDontSee('noindex', false);
    }

    public function test_admin_is_always_noindex(): void
    {
        Setting::set('hide_from_search', '0');
        $admin = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'password', 'role' => 'admin']);

        $this->actingAs($admin)->get('/admin')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_admin_toggles_search_visibility(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'password', 'role' => 'admin']);

        $this->actingAs($admin)->get('/admin/settings')->assertSee('Hide the store from Google');
        $this->post('/admin/settings', ['hide_from_search' => '0'])->assertSessionHas('success');

        Setting::flushCache();
        $this->assertSame('0', setting('hide_from_search'));
    }
}
