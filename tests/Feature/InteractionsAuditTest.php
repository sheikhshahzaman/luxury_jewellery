<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\Review;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the mutating (POST/PUT/DELETE) interactions that the storefront
 * and admin buttons trigger, beyond what ShopFlowTest already covers.
 */
class InteractionsAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'password', 'role' => 'admin']);
        $this->customer = User::create(['name' => 'Cust', 'email' => 'c@t.com', 'password' => 'password', 'role' => 'customer']);
    }

    // ---------- Customer account ----------

    public function test_profile_and_password_update(): void
    {
        $this->actingAs($this->customer);

        $this->post('/account/profile', ['name' => 'New Name', 'email' => 'c@t.com', 'phone' => '555'])
            ->assertRedirect();
        $this->assertSame('New Name', $this->customer->fresh()->name);

        $this->post('/account/password', [
            'current_password' => 'password',
            'password' => 'newpassword1', 'password_confirmation' => 'newpassword1',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(\Hash::check('newpassword1', $this->customer->fresh()->password));

        // Wrong current password is rejected.
        $this->post('/account/password', [
            'current_password' => 'wrong',
            'password' => 'another12', 'password_confirmation' => 'another12',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_address_crud_and_default(): void
    {
        $this->actingAs($this->customer);

        $this->post('/account/addresses', [
            'name' => 'Home', 'phone' => '1', 'address' => 'A St', 'city' => 'C', 'country' => 'X', 'is_default' => 1,
        ])->assertRedirect();
        $a1 = $this->customer->addresses()->firstOrFail();
        $this->assertTrue($a1->fresh()->is_default);

        $this->post('/account/addresses', [
            'name' => 'Work', 'phone' => '2', 'address' => 'B St', 'city' => 'C', 'country' => 'X',
        ]);
        $a2 = $this->customer->addresses()->latest('id')->firstOrFail();

        // Make second default -> first no longer default.
        $this->post("/account/addresses/{$a2->id}/default")->assertRedirect();
        $this->assertFalse($a1->fresh()->is_default);
        $this->assertTrue($a2->fresh()->is_default);

        // Update.
        $this->post("/account/addresses/{$a2->id}", [
            'name' => 'Work2', 'phone' => '2', 'address' => 'B St', 'city' => 'C', 'country' => 'X',
        ])->assertRedirect();
        $this->assertSame('Work2', $a2->fresh()->name);

        // Delete.
        $this->delete("/account/addresses/{$a1->id}")->assertRedirect();
        $this->assertDatabaseMissing('addresses', ['id' => $a1->id]);

        // Cannot touch another user's address.
        $other = User::create(['name' => 'O', 'email' => 'o@t.com', 'password' => 'password']);
        $otherAddr = $other->addresses()->create(['name' => 'x', 'phone' => '1', 'address' => 'z', 'city' => 'c', 'country' => 'x']);
        $this->delete("/account/addresses/{$otherAddr->id}")->assertForbidden();
    }

    // ---------- Admin resource edit/delete ----------

    public function test_admin_category_update_and_delete(): void
    {
        $this->actingAs($this->admin);
        $cat = Category::create(['name' => 'Rings', 'slug' => 'rings']);

        $this->put("/admin/categories/{$cat->slug}", ['name' => 'Fine Rings', 'is_active' => 1])
            ->assertRedirect(route('admin.categories.index'));
        $this->assertSame('fine-rings', $cat->fresh()->slug);

        $this->delete("/admin/categories/{$cat->fresh()->slug}")->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $cat->id]);
    }

    public function test_admin_coupon_update_and_delete(): void
    {
        $this->actingAs($this->admin);
        $coupon = Coupon::create(['code' => 'A', 'type' => 'percent', 'value' => 5, 'is_active' => true]);

        $this->put("/admin/coupons/{$coupon->id}", ['code' => 'B', 'type' => 'fixed', 'value' => 30, 'is_active' => 1])
            ->assertRedirect(route('admin.coupons.index'));
        $this->assertSame('B', $coupon->fresh()->code);

        $this->delete("/admin/coupons/{$coupon->id}")->assertRedirect();
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_admin_testimonial_and_bank_delete(): void
    {
        $this->actingAs($this->admin);

        $t = Testimonial::create(['name' => 'A', 'quote' => 'Q', 'rating' => 5]);
        $this->put("/admin/testimonials/{$t->id}", ['name' => 'B', 'quote' => 'Q2', 'rating' => 4])
            ->assertRedirect(route('admin.testimonials.index'));
        $this->assertSame('B', $t->fresh()->name);
        $this->delete("/admin/testimonials/{$t->id}")->assertRedirect();
        $this->assertDatabaseMissing('testimonials', ['id' => $t->id]);

        $bank = BankAccount::create(['bank_name' => 'X', 'account_name' => 'Y', 'account_number' => '1']);
        $this->delete("/admin/bank-accounts/{$bank->id}")->assertRedirect();
        $this->assertDatabaseMissing('bank_accounts', ['id' => $bank->id]);
    }

    public function test_admin_review_unapprove_and_delete(): void
    {
        $this->actingAs($this->admin);
        $cat = Category::create(['name' => 'R', 'slug' => 'r']);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'slug' => 'p', 'price' => 10, 'stock' => 1, 'is_active' => true]);
        $review = Review::create(['product_id' => $product->id, 'user_id' => $this->customer->id, 'rating' => 5, 'is_approved' => true]);
        $product->refreshRating();
        $this->assertSame(1, $product->fresh()->reviews_count);

        $this->post("/admin/reviews/{$review->id}/unapprove")->assertRedirect();
        $this->assertFalse($review->fresh()->is_approved);
        $this->assertSame(0, $product->fresh()->reviews_count);

        $this->delete("/admin/reviews/{$review->id}")->assertRedirect();
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_admin_messages_and_newsletter(): void
    {
        $this->actingAs($this->admin);

        $msg = ContactMessage::create(['name' => 'M', 'email' => 'm@t.com', 'message' => 'Hi']);
        $this->post("/admin/messages/{$msg->id}/read")->assertRedirect();
        $this->assertTrue($msg->fresh()->is_read);
        $this->delete("/admin/messages/{$msg->id}")->assertRedirect();
        $this->assertDatabaseMissing('contact_messages', ['id' => $msg->id]);

        $sub = NewsletterSubscriber::create(['email' => 's@t.com']);
        $this->get('/admin/newsletter/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->delete("/admin/newsletter/{$sub->id}")->assertRedirect();
        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $sub->id]);
    }

    public function test_admin_product_gallery_image_delete(): void
    {
        $this->actingAs($this->admin);
        $cat = Category::create(['name' => 'R', 'slug' => 'r']);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'slug' => 'p', 'price' => 10, 'stock' => 1, 'is_active' => true]);
        $image = $product->images()->create(['path' => 'https://placehold.co/80']);

        $this->delete("/admin/product-images/{$image->id}")->assertRedirect();
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }
}
