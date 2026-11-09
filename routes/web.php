<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RatesController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/search/suggest', [ShopController::class, 'suggest'])->middleware('throttle:60,1')->name('search.suggest');
Route::get('/product/{product}', [ShopController::class, 'show'])->name('product.show');

Route::get('/offers', [PageController::class, 'offers'])->name('page.offers');
Route::get('/rates', [RatesController::class, 'index'])->name('rates.index');
Route::get('/rates/live', [RatesController::class, 'live'])->middleware('throttle:120,1')->name('rates.live');
Route::get('/about', [PageController::class, 'about'])->name('page.about');
Route::get('/contact', [PageController::class, 'contact'])->name('page.contact');
Route::post('/contact', [PageController::class, 'submitContact'])->middleware('throttle:5,1')->name('page.contact.submit');
Route::post('/newsletter', [PageController::class, 'subscribe'])->middleware('throttle:10,1')->name('newsletter.subscribe');

/*
|--------------------------------------------------------------------------
| Cart & coupon (guests welcome)
|--------------------------------------------------------------------------
*/

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/update/{line}', [CartController::class, 'update'])->name('cart.update');
Route::post('/cart/remove/{line}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/coupon/apply', [CartController::class, 'applyCoupon'])->name('coupon.apply');
Route::post('/coupon/remove', [CartController::class, 'removeCoupon'])->name('coupon.remove');

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    // Rate limits: brute-force and spam protection (per IP).
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Checkout & customer account (auth required)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'place'])->middleware('throttle:10,1')->name('checkout.place');
    Route::get('/checkout/confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
    Route::get('/orders/{order}/screenshot', [CheckoutController::class, 'screenshot'])->name('orders.screenshot');

    Route::post('/wishlist/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::post('/product/{product}/review', [ReviewController::class, 'store'])->middleware('throttle:10,1')->name('product.review');

    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [AccountController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{order}/payment', [AccountController::class, 'resubmitPayment'])->middleware('throttle:10,1')->name('orders.payment');
        Route::post('/orders/{order}/cancel', [AccountController::class, 'cancelOrder'])->name('orders.cancel');
        Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
        Route::post('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
        Route::post('/password', [AccountController::class, 'updatePassword'])->name('password.update');
        Route::get('/addresses', [AccountController::class, 'addresses'])->name('addresses');
        Route::post('/addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
        Route::post('/addresses/{address}', [AccountController::class, 'updateAddress'])->name('addresses.update');
        Route::delete('/addresses/{address}', [AccountController::class, 'deleteAddress'])->name('addresses.delete');
        Route::post('/addresses/{address}/default', [AccountController::class, 'defaultAddress'])->name('addresses.default');
        Route::get('/wishlist', [AccountController::class, 'wishlist'])->name('wishlist');
    });
});

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('products', Admin\ProductController::class)->except(['show']);
    Route::delete('product-images/{image}', [Admin\ProductController::class, 'destroyImage'])->name('products.images.destroy');

    Route::resource('categories', Admin\CategoryController::class)->except(['show']);

    Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('orders/{order}/verify-payment', [Admin\OrderController::class, 'verifyPayment'])->name('orders.verify');
    Route::post('orders/{order}/reject-payment', [Admin\OrderController::class, 'rejectPayment'])->name('orders.reject');

    Route::resource('bank-accounts', Admin\BankAccountController::class)->except(['show']);
    Route::resource('coupons', Admin\CouponController::class)->except(['show']);
    Route::resource('testimonials', Admin\TestimonialController::class)->except(['show']);

    Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::post('customers/{customer}/toggle-block', [Admin\CustomerController::class, 'toggleBlock'])->name('customers.toggle-block');

    Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews/{review}/approve', [Admin\ReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('reviews/{review}/unapprove', [Admin\ReviewController::class, 'unapprove'])->name('reviews.unapprove');
    Route::delete('reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('rates', [Admin\RateController::class, 'index'])->name('rates.index');
    Route::post('rates', [Admin\RateController::class, 'update'])->name('rates.update');
    Route::post('rates/spot', [Admin\RateController::class, 'spot'])->name('rates.spot');
    Route::post('rates/fetch', [Admin\RateController::class, 'fetch'])->middleware('throttle:6,1')->name('rates.fetch');
    Route::delete('rates/{rate}', [Admin\RateController::class, 'destroy'])->name('rates.destroy');

    Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings');
    Route::post('settings', [Admin\SettingController::class, 'update'])->name('settings.update');

    Route::get('newsletter', [Admin\NewsletterController::class, 'index'])->name('newsletter.index');
    Route::get('newsletter/export', [Admin\NewsletterController::class, 'export'])->name('newsletter.export');
    Route::delete('newsletter/{subscriber}', [Admin\NewsletterController::class, 'destroy'])->name('newsletter.destroy');

    Route::get('messages', [Admin\MessageController::class, 'index'])->name('messages.index');
    Route::post('messages/{message}/read', [Admin\MessageController::class, 'toggleRead'])->name('messages.read');
    Route::delete('messages/{message}', [Admin\MessageController::class, 'destroy'])->name('messages.destroy');
});
