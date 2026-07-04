<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Hits every registered GET route as guest, customer and admin and asserts
 * none return a 5xx. Route-model bindings resolve to real seeded records so
 * parameterised URLs render their true content.
 */
class RouteAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_get_route_renders_without_error(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.com', 'password' => 'password', 'role' => 'admin']);
        $customer = User::create(['name' => 'Cust', 'email' => 'c@test.com', 'password' => 'password', 'role' => 'customer']);

        $category = Category::create(['name' => 'Rings', 'slug' => 'rings']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Test Ring', 'slug' => 'test-ring',
            'price' => 500, 'sale_price' => 400, 'stock' => 10, 'is_active' => true, 'is_featured' => true,
        ]);
        $image = ProductImage::create(['product_id' => $product->id, 'path' => 'https://placehold.co/80']);
        $bank = BankAccount::create(['bank_name' => 'Bank', 'account_name' => 'Store', 'account_number' => '123']);
        $coupon = Coupon::create(['code' => 'TEN', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        $testimonial = Testimonial::create(['name' => 'A', 'quote' => 'Great', 'rating' => 5]);
        $address = Address::create([
            'user_id' => $customer->id, 'name' => 'C', 'phone' => '1', 'address' => 'St', 'city' => 'X', 'country' => 'Y',
        ]);
        $order = Order::create([
            'user_id' => $customer->id, 'status' => 'pending', 'payment_status' => 'submitted',
            'bank_account_id' => $bank->id, 'subtotal' => 400, 'total' => 400,
            'ship_name' => 'C', 'ship_phone' => '1', 'ship_address' => 'St', 'ship_city' => 'X', 'ship_country' => 'Y',
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => 'Test Ring', 'price' => 400, 'quantity' => 1, 'line_total' => 400]);
        $review = Review::create(['product_id' => $product->id, 'user_id' => $customer->id, 'rating' => 5, 'is_approved' => true]);
        $subscriber = NewsletterSubscriber::create(['email' => 'n@test.com']);
        $message = ContactMessage::create(['name' => 'M', 'email' => 'm@test.com', 'message' => 'Hi']);

        $bindings = [
            'product' => $product, 'category' => $category, 'order' => $order,
            'bank_account' => $bank, 'coupon' => $coupon, 'testimonial' => $testimonial,
            'address' => $address, 'review' => $review, 'customer' => $customer,
            'subscriber' => $subscriber, 'message' => $message, 'image' => $image,
        ];

        // Collect resolvable GET URLs.
        $urls = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = $route->uri();
            if (preg_match('#^(_|up$|storage/)#', $uri)) {
                continue;
            }

            $skip = false;
            $url = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($bindings, &$skip) {
                $name = $m[1];
                if ($name === 'token') {
                    return 'sometoken';
                }
                if (! isset($bindings[$name])) {
                    $skip = true;

                    return $m[0];
                }

                return $bindings[$name]->getRouteKey();
            }, $uri);

            if (! $skip) {
                $urls[] = '/'.ltrim($url, '/');
            }
        }

        $this->assertNotEmpty($urls);

        $failures = [];

        $hit = function (string $role, string $url) use (&$failures) {
            try {
                $status = $this->get($url)->getStatusCode();
                if ($status >= 500) {
                    $failures[] = "$role GET $url → $status";
                }
            } catch (\Throwable $e) {
                $failures[] = "$role GET $url → EXCEPTION: ".$e->getMessage();
            }
        };

        foreach ($urls as $url) {
            $hit('guest', $url);
        }

        $this->actingAs($customer);
        foreach ($urls as $url) {
            $hit('customer', $url);
        }

        $this->actingAs($admin);
        foreach ($urls as $url) {
            $hit('admin', $url);
        }

        $this->assertEmpty($failures, "Routes returned 5xx:\n".implode("\n", $failures));
    }
}
