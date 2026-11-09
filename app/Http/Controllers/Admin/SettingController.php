<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Editable settings, grouped for the form.
     * type: text | textarea | number | image | toggle
     */
    public const GROUPS = [
        'Brand & store' => [
            'store_name' => ['label' => 'Store name (shown when no logo is set)', 'type' => 'text'],
            'logo' => ['label' => 'Logo image (replaces the store name in the header)', 'type' => 'image'],
            'store_tagline' => ['label' => 'Tagline (footer & meta description)', 'type' => 'textarea'],
            'announcement' => ['label' => 'Announcement bar text (empty to hide)', 'type' => 'text'],
            'currency_symbol' => ['label' => 'Currency symbol', 'type' => 'text'],
        ],
        'Checkout' => [
            'shipping_fee' => ['label' => 'Flat shipping fee', 'type' => 'number'],
            'free_shipping_min' => ['label' => 'Free shipping above (0 to disable)', 'type' => 'number'],
            'tax_rate' => ['label' => 'Tax rate % (0 to disable)', 'type' => 'number'],
        ],
        'Search engines' => [
            'hide_from_search' => ['label' => 'Hide the store from Google and other search engines (noindex, nofollow) — untick when you launch', 'type' => 'toggle', 'default' => '1'],
        ],
        'Payments' => [
            'cod_enabled' => ['label' => 'Offer cash on delivery at checkout', 'type' => 'toggle', 'default' => '1'],
            'cod_fee' => ['label' => 'Cash on delivery fee (0 for none)', 'type' => 'number'],
        ],
        'Contact & map' => [
            'contact_email' => ['label' => 'Contact email', 'type' => 'text'],
            'contact_phone' => ['label' => 'Contact phone', 'type' => 'text'],
            'contact_address' => ['label' => 'Address (also used for the map)', 'type' => 'text'],
            'map_latitude' => ['label' => 'Map latitude (e.g. 38.7071 — shows a reliable OpenStreetMap)', 'type' => 'text'],
            'map_longitude' => ['label' => 'Map longitude (e.g. -9.1355)', 'type' => 'text'],
            'map_embed_url' => ['label' => 'Map embed URL / iframe (optional — overrides lat/lng & address)', 'type' => 'text'],
            'instagram_handle' => ['label' => 'Instagram handle', 'type' => 'text'],
            'instagram_url' => ['label' => 'Instagram URL', 'type' => 'text'],
        ],
        'Homepage — opening (hero)' => [
            'hero_eyebrow' => ['label' => 'Small line above the headline', 'type' => 'text'],
            'hero_title' => ['label' => 'Headline (HTML <em> allowed)', 'type' => 'text'],
            'hero_text' => ['label' => 'Intro paragraph', 'type' => 'textarea'],
            'hero_image' => ['label' => 'Opening image (only used if the scroll film’s frames are missing)', 'type' => 'image'],
        ],
        'Homepage — section headings' => [
            'categories_title' => ['label' => 'Categories section title', 'type' => 'text'],
            'bestsellers_eyebrow' => ['label' => 'Best sellers eyebrow', 'type' => 'text'],
            'bestsellers_title' => ['label' => 'Best sellers section title', 'type' => 'text'],
            'testimonials_title' => ['label' => 'Testimonials section title', 'type' => 'text'],
        ],
        'Homepage — opening story (why buy from us)' => [
            'story_1_title' => ['label' => 'Chapter 1 title (made your way)', 'type' => 'text'],
            'story_1_text' => ['label' => 'Chapter 1 text', 'type' => 'textarea'],
            'story_2_title' => ['label' => 'Chapter 2 title (gold rate)', 'type' => 'text'],
            'story_2_text' => ['label' => 'Chapter 2 text', 'type' => 'textarea'],
            'story_3_title' => ['label' => 'Chapter 3 title (payment)', 'type' => 'text'],
            'story_3_text' => ['label' => 'Chapter 3 text', 'type' => 'textarea'],
            'story_4_title' => ['label' => 'Chapter 4 title (delivery)', 'type' => 'text'],
            'story_4_text' => ['label' => 'Chapter 4 text', 'type' => 'textarea'],
            'story_5_title' => ['label' => 'Chapter 5 title (your metal)', 'type' => 'text'],
            'story_5_text' => ['label' => 'Chapter 5 text', 'type' => 'textarea'],
        ],
        'Homepage — banner & marquee' => [
            'banner_eyebrow' => ['label' => 'Banner eyebrow', 'type' => 'text'],
            'banner_title' => ['label' => 'Banner title', 'type' => 'text'],
            'banner_text' => ['label' => 'Banner text', 'type' => 'textarea'],
            'banner_image' => ['label' => 'Banner image', 'type' => 'image'],
            'banner_pill' => ['label' => 'Banner pill text (e.g. "Up to 30% off")', 'type' => 'text'],
            'marquee_text' => ['label' => 'Marquee items (separate with |)', 'type' => 'text'],
        ],
        'Homepage — value props' => [
            'value_1_title' => ['label' => 'Value 1 title', 'type' => 'text'],
            'value_1_text' => ['label' => 'Value 1 text', 'type' => 'text'],
            'value_2_title' => ['label' => 'Value 2 title', 'type' => 'text'],
            'value_2_text' => ['label' => 'Value 2 text', 'type' => 'text'],
            'value_3_title' => ['label' => 'Value 3 title', 'type' => 'text'],
            'value_3_text' => ['label' => 'Value 3 text', 'type' => 'text'],
            'value_4_title' => ['label' => 'Value 4 title', 'type' => 'text'],
            'value_4_text' => ['label' => 'Value 4 text', 'type' => 'text'],
        ],
        'Homepage — newsletter & gallery' => [
            'newsletter_title' => ['label' => 'Newsletter heading', 'type' => 'text'],
            'newsletter_text' => ['label' => 'Newsletter paragraph', 'type' => 'textarea'],
            'gallery_images' => ['label' => 'Instagram gallery image URLs (JSON array, max 5)', 'type' => 'textarea'],
        ],
        'About page' => [
            'about_title' => ['label' => 'About page title', 'type' => 'text'],
            'about_eyebrow' => ['label' => 'About eyebrow', 'type' => 'text'],
            'about_heading' => ['label' => 'About heading', 'type' => 'text'],
            'about_text' => ['label' => 'About text', 'type' => 'textarea'],
            'about_image' => ['label' => 'About image', 'type' => 'image'],
            'about_image_small' => ['label' => 'About small image', 'type' => 'image'],
        ],
        'Offers page' => [
            'offers_title' => ['label' => 'Offers page title', 'type' => 'text'],
            'offers_intro' => ['label' => 'Offers page intro', 'type' => 'textarea'],
        ],
    ];

    public function edit(): View
    {
        return view('admin.settings', [
            'groups' => self::GROUPS,
            'values' => Setting::query()->pluck('value', 'key'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Validate every field up front so a bad value saves nothing.
        $rules = [];
        foreach (self::GROUPS as $fields) {
            foreach ($fields as $key => $meta) {
                $rules += match ($meta['type'] ?? 'text') {
                    'number' => [$key => ['nullable', 'numeric', 'min:0', 'max:1000000']],
                    'toggle' => [$key => ['nullable', 'boolean']],
                    'image' => [
                        $key => ['nullable', 'string', 'max:1000'],
                        $key.'_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                    ],
                    default => [$key => ['nullable', 'string', 'max:5000']],
                };
            }
        }
        $rules['tax_rate'][] = 'max:100';
        $request->validate($rules, [], ['*_file' => 'image']);

        foreach (self::GROUPS as $fields) {
            foreach ($fields as $key => $meta) {
                $type = $meta['type'] ?? 'text';

                if ($type === 'image') {
                    $this->saveImage($request, $key);
                } elseif ($type === 'toggle') {
                    // The form sends a hidden "0" before the checkbox.
                    if ($request->has($key)) {
                        Setting::set($key, $request->boolean($key) ? '1' : '0');
                    }
                } elseif ($request->has($key)) {
                    $value = (string) $request->input($key);

                    // The hero title is rendered as HTML; only <em> is allowed.
                    if ($key === 'hero_title') {
                        $value = strip_tags($value, '<em>');
                        $value = preg_replace('/<em\b[^>]*>/i', '<em>', $value);
                    }

                    Setting::set($key, $value);
                }
            }
        }

        return back()->with('success', 'Settings saved.');
    }

    protected function saveImage(Request $request, string $key): void
    {
        // Remove.
        if ($request->boolean($key.'_clear')) {
            $this->deleteExisting($key);
            Setting::set($key, '');

            return;
        }

        // Upload a file (takes priority over a URL). Already validated above;
        // SVG is not accepted because it can carry scripts.
        if ($request->hasFile($key.'_file')) {
            $this->deleteExisting($key);
            Setting::set($key, $request->file($key.'_file')->store('branding', 'public'));

            return;
        }

        // Otherwise accept a pasted URL (only overwrite when non-empty).
        $url = trim((string) $request->input($key, ''));
        if ($url !== '' && $url !== (string) setting($key)) {
            $this->deleteExisting($key);
            Setting::set($key, $url);
        }
    }

    protected function deleteExisting(string $key): void
    {
        $current = (string) setting($key);
        if ($current !== '' && ! Str::startsWith($current, ['http://', 'https://'])) {
            Storage::disk('public')->delete($current);
        }
    }
}
