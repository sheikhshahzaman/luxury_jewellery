<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedSettings();
        $categories = $this->seedCategories();
        $products = $this->seedProducts($categories);
        $this->seedTestimonials();
        $this->seedBankAccounts();
        $this->seedCoupons();
        $this->seedOrdersAndReviews($products);
        $this->seedNewsletter();
        $this->seedMessages();
    }

    protected function seedUsers(): void
    {
        User::create([
            'name' => 'Store Admin',
            'email' => 'admin@eluxee.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        // Primary demo customer (used for login).
        User::create([
            'name' => 'Amira Khan',
            'email' => 'customer@eluxee.test',
            'password' => 'password',
            'phone' => '+92 300 1234567',
            'role' => 'customer',
        ]);

        // Additional real customers so the admin shows realistic volume.
        $extra = [
            ['Daniel Rivera', 'daniel.rivera@example.com', '+44 7700 900123'],
            ['Sofia Marino', 'sofia.marino@example.com', '+39 320 1234567'],
            ['Liam O\'Connor', 'liam.oconnor@example.com', '+353 85 1234567'],
            ['Yuki Tanaka', 'yuki.tanaka@example.com', '+81 90 1234 5678'],
            ['Noor Al-Farsi', 'noor.alfarsi@example.com', '+971 50 1234567'],
            ['Emma Larsson', 'emma.larsson@example.com', '+46 70 123 45 67'],
            ['Carlos Mendes', 'carlos.mendes@example.com', '+351 912 345 678'],
        ];
        foreach ($extra as [$name, $email, $phone]) {
            User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => 'password',
                'role' => 'customer',
            ]);
        }

        // Block one customer to demonstrate the blocked state.
        User::where('email', 'carlos.mendes@example.com')->update(['is_blocked' => true]);
    }

    protected function seedSettings(): void
    {
        $defaults = [
            'store_name' => 'Eluxee',
            'store_tagline' => 'Fine jewelry handcrafted in small batches since 1999. Recycled gold, traceable stones, lifetime care.',
            'announcement' => 'Complimentary shipping on orders over $150 ✦ Easy 30 day returns',
            'currency_symbol' => '$',
            'shipping_fee' => '15',
            'free_shipping_min' => '150',
            'tax_rate' => '0',
            'contact_email' => 'care@eluxee.com',
            'contact_phone' => '+1 (555) 010-1999',
            'contact_address' => 'Atelier 12, Rua do Ouro, Lisbon, Portugal',
            'instagram_handle' => '@eluxee',
            'instagram_url' => 'https://instagram.com',
            'marquee_text' => 'Fine Jewelry|Handcrafted|Ethically Sourced|Recycled Gold|Since 1999',
            'banner_eyebrow' => 'Seasonal drop',
            'banner_title' => 'The Gilded Hour Collection',
            'banner_text' => 'Warm gold tones, soft silhouettes, and stones chosen for evening light. A limited run of 200 numbered pieces.',
            'banner_image' => 'https://images.unsplash.com/photo-1602173574767-37ac01994b2a?w=1000&q=80',
            'banner_pill' => 'Up to 30% off',
            'categories_title' => 'Shop by category',
            'bestsellers_eyebrow' => 'Most loved',
            'bestsellers_title' => 'Best sellers',
            'testimonials_title' => 'Loved by our customers',
            'newsletter_title' => 'Receive first access to new pieces',
            'newsletter_text' => 'Early collection previews, private offers, and care guides. One email a month, nothing more.',
            'value_1_title' => 'Ethically sourced',
            'value_1_text' => 'Traceable stones and certified recycled 18k gold in every piece.',
            'value_2_title' => 'Free worldwide shipping',
            'value_2_text' => 'Insured express delivery on all orders over $150.',
            'value_3_title' => 'Lifetime warranty',
            'value_3_text' => 'Complimentary resizing, polishing and repairs, forever.',
            'value_4_title' => 'Secure checkout',
            'value_4_text' => 'Verified bank transfer with a simple upload-and-confirm flow.',
            'offers_title' => 'Offers & coupons',
            'offers_intro' => 'Copy a code below and paste it in your cart to save on your next order. Codes are limited — use them while they last.',
            'map_latitude' => '38.7071',
            'map_longitude' => '-9.1355',
            'map_embed_url' => '',
            'gallery_images' => json_encode([
                'https://images.unsplash.com/photo-1617038220319-276d3cfab638?w=500&q=80',
                'https://images.unsplash.com/photo-1620656798579-1984d9e87df7?w=500&q=80',
                'https://images.unsplash.com/photo-1600003263720-95b45a4035d5?w=500&q=80',
                'https://images.unsplash.com/photo-1602173574767-37ac01994b2a?w=500&q=80',
                'https://images.unsplash.com/photo-1590166223826-12dee1677420?w=500&q=80',
            ]),
        ];

        foreach ($defaults as $key => $value) {
            Setting::set($key, $value);
        }
    }

    /** @return array<string, Category> */
    protected function seedCategories(): array
    {
        $rows = [
            ['name' => 'Rings', 'image' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=700&q=80', 'description' => 'Solitaires, bands and statement rings in recycled 18k gold.'],
            ['name' => 'Necklaces', 'image' => 'https://images.unsplash.com/photo-1601121141461-9d6647bca1ed?w=700&q=80', 'description' => 'Pendants and chains made to be layered and kept.'],
            ['name' => 'Earrings', 'image' => 'https://images.unsplash.com/photo-1590166223826-12dee1677420?w=700&q=80', 'description' => 'Studs, hoops and sculptural pairs for every hour.'],
            ['name' => 'Bracelets', 'image' => 'https://images.unsplash.com/photo-1610694955371-d4a3e0ce4b52?w=700&q=80', 'description' => 'Cuffs and chains finished by hand in our atelier.'],
        ];

        $categories = [];
        foreach ($rows as $i => $row) {
            $categories[$row['name']] = Category::create([
                'name' => $row['name'],
                'slug' => Str::slug($row['name']),
                'description' => $row['description'],
                'image' => $row['image'],
                'sort_order' => $i,
            ]);
        }

        return $categories;
    }

    /** @return \Illuminate\Support\Collection<int, Product> */
    protected function seedProducts(array $categories)
    {
        $img = fn (string $id, int $w = 800) => "https://images.unsplash.com/{$id}?w={$w}&q=80";

        $pool = [
            'photo-1515562141207-7a88fb7ce338', 'photo-1599643478518-a784e5dc4c8f',
            'photo-1611591437281-460bfbe1220a', 'photo-1611652022419-a9419f74343d',
            'photo-1608042314453-ae338d80c427', 'photo-1535632066927-ab7c9ab60908',
            'photo-1605100804763-247f67b3557e', 'photo-1601121141461-9d6647bca1ed',
            'photo-1590166223826-12dee1677420', 'photo-1610694955371-d4a3e0ce4b52',
            'photo-1617038220319-276d3cfab638', 'photo-1620656798579-1984d9e87df7',
            'photo-1600003263720-95b45a4035d5', 'photo-1602173574767-37ac01994b2a',
        ];

        $details = "Materials\n· Recycled 18k gold\n· Ethically sourced stones\n· Signed and numbered certificate\n\nCare\n· Store in the provided pouch\n· Complimentary lifetime polishing and resizing";

        $rows = [
            // name, category, price, sale, stock, tag, featured, image
            ['Aurelia Band Duo', 'Rings', 860, 780, 14, 'Bestseller', true, 0],
            ['Solene Solitaire', 'Rings', 1240, null, 9, 'New', true, 1],
            ['Ivy Gemstone Set', 'Necklaces', 720, null, 18, null, true, 2],
            ['Muse Twin Rings', 'Rings', 540, 480, 22, null, true, 3],
            ['Elara Sculpt Pair', 'Earrings', 980, null, 7, 'Limited', true, 4],
            ['Vermeil Heritage Set', 'Necklaces', 1480, null, 5, null, true, 5],
            ['Signet of Hours', 'Rings', 690, null, 16, null, false, 6],
            ['Cascade Pendant', 'Necklaces', 890, 790, 12, 'Bestseller', false, 7],
            ['Petite Orbit Studs', 'Earrings', 420, null, 30, null, false, 8],
            ['Linea Chain Cuff', 'Bracelets', 640, null, 11, null, false, 9],
            ['Gilded Hour Choker', 'Necklaces', 1120, 990, 6, 'Limited', false, 10],
            ['Atelier Pearl Drops', 'Earrings', 560, null, 20, null, false, 11],
            ['Meridian Bangle', 'Bracelets', 750, null, 13, null, false, 12],
            ['Lumen Stacking Ring', 'Rings', 380, 340, 40, null, false, 13],
            ['Sable Rope Chain', 'Necklaces', 830, null, 15, null, false, 0],
            ['Crescent Ear Cuffs', 'Earrings', 460, null, 24, 'New', false, 1],
            ['Odessa Tennis Bracelet', 'Bracelets', 1650, 1480, 4, 'Limited', false, 2],
            ['Nova Trio Band', 'Rings', 720, null, 17, null, false, 3],
            ['Voile Lariat', 'Necklaces', 940, null, 8, null, false, 4],
            ['Ember Huggie Hoops', 'Earrings', 510, 450, 26, null, false, 5],
        ];

        $products = collect();
        foreach ($rows as $i => [$name, $cat, $price, $sale, $stock, $tag, $featured, $imgIdx]) {
            $product = Product::create([
                'category_id' => $categories[$cat]->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'sku' => 'ELX-'.strtoupper(substr($cat, 0, 1)).'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'description' => "The {$name} is cast in small batches from recycled 18k gold and finished entirely by hand. Designed to be worn daily and kept for decades.",
                'details' => $details,
                'price' => $price,
                'sale_price' => $sale,
                'stock' => $stock,
                'image' => $img($pool[$imgIdx]),
                'tag' => $tag,
                'is_featured' => $featured,
                'is_active' => true,
            ]);

            // Two extra gallery images from the pool.
            foreach ([($imgIdx + 3) % count($pool), ($imgIdx + 7) % count($pool)] as $order => $poolIdx) {
                $product->images()->create([
                    'path' => $img($pool[$poolIdx]),
                    'sort_order' => $order,
                ]);
            }

            $products->push($product);
        }

        return $products;
    }

    protected function seedTestimonials(): void
    {
        $rows = [
            ['quote' => 'The Solene ring exceeded every expectation. The weight, the finish, the box it arrived in. Flawless.', 'name' => 'Amira K.', 'location' => 'Dubai'],
            ['quote' => 'I bought the Aurelia duo for our anniversary. My wife has not taken them off since. Worth every dollar.', 'name' => 'Daniel R.', 'location' => 'London'],
            ['quote' => 'Customer care resized my ring in four days, free of charge. This is how luxury service should feel.', 'name' => 'Sofia M.', 'location' => 'Milan'],
        ];

        foreach ($rows as $i => $row) {
            Testimonial::create($row + ['rating' => 5, 'sort_order' => $i]);
        }
    }

    protected function seedBankAccounts(): void
    {
        BankAccount::create([
            'bank_name' => 'Meezan Bank',
            'account_name' => 'Eluxee Jewellery (Pvt) Ltd',
            'account_number' => '0101-0987654321',
            'iban' => 'PK36MEZN0001010987654321',
            'branch' => 'Main Boulevard Branch',
            'instructions' => 'Please use your full name as the transfer reference and upload a clear screenshot of the confirmation.',
            'sort_order' => 0,
        ]);

        BankAccount::create([
            'bank_name' => 'HBL',
            'account_name' => 'Eluxee Jewellery (Pvt) Ltd',
            'account_number' => '2233-4455667788',
            'iban' => 'PK80HABB0022334455667788',
            'branch' => 'Clifton Branch',
            'instructions' => 'Transfers usually verify within 2 business hours.',
            'sort_order' => 1,
        ]);
    }

    protected function seedCoupons(): void
    {
        Coupon::create([
            'code' => 'WELCOME10',
            'type' => 'percent',
            'value' => 10,
            'min_order' => 100,
            'is_active' => true,
            'is_public' => true,
        ]);

        Coupon::create([
            'code' => 'GILDED50',
            'type' => 'fixed',
            'value' => 50,
            'min_order' => 500,
            'max_uses' => 100,
            'expires_at' => now()->addMonths(2),
            'is_active' => true,
            'is_public' => true,
        ]);

        // A private code (won't show on the public offers page).
        Coupon::create([
            'code' => 'VIP15',
            'type' => 'percent',
            'value' => 15,
            'min_order' => 300,
            'is_active' => true,
            'is_public' => false,
        ]);
    }

    protected function seedOrdersAndReviews($products): void
    {
        $customers = User::where('role', 'customer')->orderBy('id')->get()->values();
        $banks = BankAccount::orderBy('id')->get();
        $coupons = Coupon::all()->keyBy('code');
        $screenshot = $this->makeDemoScreenshot();

        // Shipping location per customer index.
        $locations = [
            ['Amira Khan', 'House 12, Street 4, DHA Phase 5', 'Lahore', 'Pakistan'],
            ['Daniel Rivera', '18 Camden High Street', 'London', 'United Kingdom'],
            ['Sofia Marino', 'Via Montenapoleone 8', 'Milan', 'Italy'],
            ["Liam O'Connor", '42 Grafton Street', 'Dublin', 'Ireland'],
            ['Yuki Tanaka', '3-1 Marunouchi, Chiyoda', 'Tokyo', 'Japan'],
            ['Noor Al-Farsi', 'Jumeirah Beach Road 55', 'Dubai', 'United Arab Emirates'],
            ['Emma Larsson', 'Drottninggatan 21', 'Stockholm', 'Sweden'],
            ['Carlos Mendes', 'Rua Augusta 100', 'Lisbon', 'Portugal'],
        ];

        // [customerIdx, monthsAgo, status, payment, [productIdx...], couponCode|null]
        $specs = [
            [0, 5, 'delivered', 'verified', [0, 2], null],
            [1, 5, 'delivered', 'verified', [4], null],
            [2, 4, 'delivered', 'verified', [7, 13], 'WELCOME10'],
            [0, 4, 'delivered', 'verified', [5], null],
            [3, 3, 'delivered', 'verified', [1], null],
            [4, 3, 'delivered', 'verified', [8, 9], null],
            [5, 3, 'shipped', 'verified', [10], null],
            [1, 2, 'delivered', 'verified', [11], 'GILDED50'],
            [2, 2, 'shipped', 'verified', [12], null],
            [6, 2, 'delivered', 'verified', [3, 6], null],
            [0, 1, 'processing', 'verified', [14], null],
            [3, 1, 'shipped', 'verified', [15], null],
            [4, 1, 'cancelled', 'verified', [16], null],
            [5, 1, 'delivered', 'verified', [17], null],
            [0, 0, 'processing', 'verified', [18], null],
            [1, 0, 'pending', 'submitted', [19], null],
            [2, 0, 'pending', 'submitted', [2], null],
            [6, 0, 'pending', 'rejected', [4], null],
            [7, 0, 'pending', 'submitted', [7, 8], null],
        ];

        foreach ($specs as [$custIdx, $months, $status, $payment, $itemIdx, $couponCode]) {
            $customer = $customers[$custIdx];
            [$shipName, $shipAddr, $shipCity, $shipCountry] = $locations[$custIdx];
            $when = now()->subMonths($months)->subDays(random_int(0, 20))->subHours(random_int(0, 22));

            $items = collect($itemIdx)->map(fn ($i) => $products[$i]);
            $subtotal = round($items->sum(fn ($p) => $p->current_price), 2);

            $discount = 0;
            if ($couponCode && $coupons->has($couponCode)) {
                $discount = $coupons[$couponCode]->discountFor($subtotal);
            }
            $shipping = ($subtotal - $discount) >= 150 ? 0 : 15;
            $total = round($subtotal - $discount + $shipping, 2);

            $order = Order::create([
                'user_id' => $customer->id,
                'status' => $status,
                'payment_status' => $payment,
                'bank_account_id' => $banks->random()->id,
                'payment_screenshot' => $screenshot,
                'payment_reference' => 'TRX-'.random_int(100000, 999999),
                'payment_verified_at' => $payment === 'verified' ? $when->copy()->addHours(3) : null,
                'payment_note' => $payment === 'rejected'
                    ? 'The transferred amount did not match the order total. Please resend a corrected receipt.'
                    : null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'coupon_code' => $discount > 0 ? $couponCode : null,
                'shipping_fee' => $shipping,
                'tax' => 0,
                'total' => $total,
                'ship_name' => $shipName,
                'ship_phone' => $customer->phone,
                'ship_address' => $shipAddr,
                'ship_city' => $shipCity,
                'ship_country' => $shipCountry,
                'tracking_number' => in_array($status, ['shipped', 'delivered']) ? 'ELX'.random_int(10000000, 99999999) : null,
                'shipped_at' => in_array($status, ['shipped', 'delivered']) ? $when->copy()->addDay() : null,
                'delivered_at' => $status === 'delivered' ? $when->copy()->addDays(4) : null,
                'cancelled_at' => $status === 'cancelled' ? $when->copy()->addDays(1) : null,
            ]);

            foreach ($items as $product) {
                $qty = random_int(1, 2);
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_image' => $product->image,
                    'price' => $product->current_price,
                    'quantity' => $qty,
                    'line_total' => round($product->current_price * $qty, 2),
                ]);
            }

            // Recompute totals from the (randomised) quantities.
            $realSubtotal = round($order->items()->sum('line_total'), 2);
            $realDiscount = ($couponCode && $coupons->has($couponCode)) ? $coupons[$couponCode]->discountFor($realSubtotal) : 0;
            $realShipping = ($realSubtotal - $realDiscount) >= 150 ? 0 : 15;
            $order->forceFill([
                'subtotal' => $realSubtotal,
                'discount' => $realDiscount,
                'shipping_fee' => $realShipping,
                'total' => round($realSubtotal - $realDiscount + $realShipping, 2),
            ])->saveQuietly();

            if ($realDiscount > 0) {
                $coupons[$couponCode]->increment('used_count');
            }

            // Backdate for the dashboard chart.
            $order->timestamps = false;
            $order->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
        }

        // Reviews from real buyers — a mix of approved (live) and pending (moderation queue).
        // [productIdx, customerIdx, rating, comment, approved]
        $reviewData = [
            [0, 0, 5, 'The weight and finish are extraordinary. It arrived beautifully boxed with its numbered certificate.', true],
            [2, 2, 5, 'Even better in person — the stones catch the light constantly. Sizing was perfect.', true],
            [4, 4, 4, 'Gorgeous pair, exactly as photographed. Shipping took a day longer than expected.', true],
            [7, 4, 5, 'My third piece from this atelier. The pendant sits perfectly and the chain feels substantial.', true],
            [5, 6, 5, 'A heirloom in the making. The craftsmanship is unmatched.', true],
            [1, 3, 5, 'Absolutely stunning solitaire — still waiting on the complimentary resize but thrilled so far.', false],
            [8, 4, 4, 'Delicate studs, exactly as pictured. Would love more color options.', false],
        ];

        foreach ($reviewData as [$pIdx, $cIdx, $rating, $comment, $approved]) {
            Review::create([
                'product_id' => $products[$pIdx]->id,
                'user_id' => $customers[$cIdx]->id,
                'rating' => $rating,
                'comment' => $comment,
                'is_approved' => $approved,
            ]);
        }

        // Refresh cached ratings (counts approved reviews only).
        Product::all()->each->refreshRating();
    }

    protected function seedNewsletter(): void
    {
        $emails = [
            'sophie.dubois@example.com', 'daniel.rivera@example.com', 'amira.k@example.com',
            'marco.rossi@example.com', 'hannah.schmidt@example.com', 'priya.nair@example.com',
            'olivia.brown@example.com', 'kenji.sato@example.com', 'lucia.ferrari@example.com',
        ];
        foreach ($emails as $email) {
            NewsletterSubscriber::create(['email' => $email]);
        }
    }

    protected function seedMessages(): void
    {
        $messages = [
            ['Isabelle Laurent', 'isabelle.l@example.com', '+33 6 12 34 56 78', 'Ring resize', 'Hello, I ordered the Solene Solitaire last week. Could I request a resize to EU 54 before it ships? Thank you.', false],
            ['George Whitfield', 'g.whitfield@example.com', null, 'Engraving options', 'Do you offer engraving on the Aurelia band duo? I would like a short date inside the band.', false],
            ['Mei Lin', 'mei.lin@example.com', '+65 8123 4567', 'International shipping', 'Is insured express shipping available to Singapore, and how long does verification of the bank transfer usually take?', false],
            ['Thomas Berg', 'thomas.berg@example.com', null, 'Thank you', 'Just received the Cascade pendant — the packaging and finish exceeded expectations. Wonderful service.', true],
            ['Ana Costa', 'ana.costa@example.com', '+351 913 000 111', 'Care guide', 'Could you send the care instructions for recycled 18k gold? I want to keep my bracelet looking new.', true],
        ];

        foreach ($messages as [$name, $email, $phone, $subject, $message, $isRead]) {
            ContactMessage::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'subject' => $subject,
                'message' => $message,
                'is_read' => $isRead,
            ]);
        }
    }

    /** Create a demo payment screenshot on the private disk; returns its path. */
    protected function makeDemoScreenshot(): string
    {
        $path = 'payments/demo-receipt.png';

        if (function_exists('imagecreatetruecolor')) {
            $im = imagecreatetruecolor(620, 400);
            $bg = imagecolorallocate($im, 243, 239, 231);
            $ink = imagecolorallocate($im, 30, 41, 33);
            $gold = imagecolorallocate($im, 195, 165, 114);
            imagefilledrectangle($im, 0, 0, 620, 400, $bg);
            imagefilledrectangle($im, 0, 0, 620, 70, $ink);
            imagestring($im, 5, 24, 26, 'DEMO BANK - TRANSFER CONFIRMATION', $bg);
            imagestring($im, 4, 24, 100, 'Status:    SUCCESSFUL', $ink);
            imagestring($im, 4, 24, 140, 'Amount:    (demo screenshot)', $ink);
            imagestring($im, 4, 24, 180, 'To:        Eluxee Jewellery (Pvt) Ltd', $ink);
            imagestring($im, 4, 24, 220, 'Reference: TRX-000000', $ink);
            imagestring($im, 3, 24, 300, 'This image was generated by the database seeder.', $gold);
            ob_start();
            imagepng($im);
            $png = ob_get_clean();
            imagedestroy($im);
        } else {
            // 1x1 fallback pixel.
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        }

        Storage::disk('local')->put($path, $png);

        return $path;
    }
}
