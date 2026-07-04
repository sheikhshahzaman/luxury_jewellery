# Eluxee — Jewellery Ecommerce (Laravel)

Full ecommerce store built on the Eluxee luxury jewellery theme. Payment is by
**direct bank transfer**: the admin configures bank accounts, the customer
transfers the order total, uploads a screenshot of the receipt at checkout, and
the admin verifies (or rejects) the payment before fulfilment.

## Quick start

```bash
composer install
cp .env.example .env          # only on a fresh clone
php artisan key:generate      # only on a fresh clone
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Open http://127.0.0.1:8000

### Demo accounts

| Role     | Email                | Password   |
|----------|----------------------|------------|
| Admin    | admin@eluxee.test    | `password` |
| Customer | customer@eluxee.test | `password` |

Admin panel: http://127.0.0.1:8000/admin

## Storefront

- Home page (hero, categories, best sellers, banner, values, editorial, testimonials, Instagram gallery, newsletter) — all content managed from the admin
- Shop with category filter, price range, search, sorting and pagination
- **Live search suggestions** — type in the header search to get instant product matches (thumbnail, category, price)
- Product page with image gallery, sale pricing, stock states, verified-buyer reviews and related pieces
- **Wishlist heart on every product card** (shop, home, related) plus the product page; guests are routed to sign in
- **Offers page** (`/offers`) listing the coupon codes the admin marks public, with one-click copy
- **Contact page map** — OpenStreetMap by coordinates (no API key) or a pasted Google/OSM embed, admin-configurable
- Session cart with quantity clamping to stock, coupons (percent / fixed, min order, usage limits, validity window)
- Flat shipping fee with free-shipping threshold and optional tax rate (settings)
- Checkout (login required): shipping details, choose one of the admin's bank accounts, **upload the transfer screenshot (required)**, optional transaction reference
- Customer account: dashboard, orders with status timeline, re-upload payment proof after a rejection, cancel pending orders (stock restored), addresses, profile/password, wishlist
- Auth: register, login, logout, password reset; blocked accounts are locked out
- Contact form and newsletter signup

## Admin panel (`/admin`)

- Dashboard: verified revenue, payments awaiting verification, 6-month sales chart, low stock, recent orders, top sellers
- Products: CRUD with main image (upload or URL), multi-image gallery, sale price, stock, tags, featured/active flags
- Categories: CRUD with images and ordering
- Orders: filter by status/payment, order detail with **payment verification** (view screenshot → verify or reject with a reason sent to the customer), status flow (pending → processing → shipped → delivered / cancelled), tracking number, internal notes; cancelling restores stock
- Bank accounts: CRUD — these are exactly what customers see at checkout
- Coupons, testimonials: CRUD
- Customers: list, detail with order history, block/unblock
- Reviews: approve / hide / delete (product rating recalculates)
- Newsletter subscribers (CSV export) and contact messages
- Coupons carry an **is_public** flag controlling whether they appear on the storefront Offers page
- Settings — everything on the storefront is editable here: **logo image upload**, store name, tagline, announcement bar, currency, shipping fee + free-shipping threshold + tax, contact info, **map coordinates/embed**, and all home/about/offers page text plus the hero, banner, about and value-prop **images**

## Payment flow

1. Customer places order at checkout with a screenshot → order `pending`, payment `submitted`, stock reserved.
2. Admin opens the order, checks the screenshot (served privately, owner/admin only) and **verifies** → payment `verified`, order `processing` — or **rejects** with a reason.
3. On rejection the customer sees the reason on their order page and can upload a new screenshot → back to `submitted`.

## Notes

- SQLite by default; switch `DB_*` in `.env` for MySQL.
- Payment screenshots are stored on the private disk (`storage/app/private/payments`) and served only to the order's owner or an admin.
- Demo product images are Unsplash hotlinks; replace them with your own photography from the admin product form (uploads land in `storage/app/public/products`).
- Tests: `php artisan test` (30 tests cover the full shop, checkout, payment verification and admin flows).

Theme source: `../theme-source/eluxee/eluxee-laravel` (original landing page package this store was built from).

# luxury_jewellery
