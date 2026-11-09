<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    /**
     * SQL for the effective (sale-aware) price.
     * CAST keeps the comparison numeric — SQLite's COALESCE otherwise strips
     * column affinity, which makes string-bound range values compare wrong.
     */
    public const EFFECTIVE_PRICE_SQL = 'CAST(COALESCE(sale_price, price) AS DECIMAL(10,2))';

    /** Longest engraving text a shopper may request. */
    public const ENGRAVING_MAX = 24;

    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'description', 'details',
        'price', 'sale_price', 'stock', 'image', 'tag',
        'is_featured', 'is_active', 'rating_avg', 'reviews_count',
        'metal_options', 'size_options', 'size_label', 'engraving_enabled', 'engraving_fee',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'rating_avg' => 'decimal:2',
        'engraving_fee' => 'decimal:2',
        'metal_options' => 'array',
        'size_options' => 'array',
        'engraving_enabled' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    public function getCurrentPriceAttribute(): float
    {
        return (float) ($this->sale_price ?? $this->price);
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->sale_price !== null && (float) $this->sale_price < (float) $this->price;
    }

    public function getDiscountPercentAttribute(): int
    {
        if (! $this->has_discount) {
            return 0;
        }

        return (int) round((1 - ((float) $this->sale_price / (float) $this->price)) * 100);
    }

    public function getImageUrlAttribute(): string
    {
        return self::resolveImage($this->image, $this->name);
    }

    public function getInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    /**
     * All gallery URLs: main image first, then extra images.
     */
    public function getGalleryAttribute(): array
    {
        $urls = [$this->image_url];
        foreach ($this->images as $img) {
            $urls[] = self::resolveImage($img->path, $this->name);
        }

        return array_values(array_unique($urls));
    }

    // ------------------------------------------------------------------
    // Jewellery options (metal, size, engraving)
    // ------------------------------------------------------------------

    /** @return array<int, array{name: string, price: float}> */
    public function metals(): array
    {
        return collect($this->metal_options ?? [])
            ->map(fn ($m) => ['name' => trim((string) ($m['name'] ?? '')), 'price' => round((float) ($m['price'] ?? 0), 2)])
            ->filter(fn ($m) => $m['name'] !== '')
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function sizes(): array
    {
        return collect($this->size_options ?? [])
            ->map(fn ($s) => trim((string) $s))
            ->filter(fn ($s) => $s !== '')
            ->values()
            ->all();
    }

    public function getSizeTitleAttribute(): string
    {
        return $this->size_label ?: 'Size';
    }

    /** True when the shopper must pick a metal or size before adding to cart. */
    public function requiresChoice(): bool
    {
        return $this->metals() !== [] || $this->sizes() !== [];
    }

    public function hasOptions(): bool
    {
        return $this->requiresChoice() || $this->engraving_enabled;
    }

    /**
     * Validate a shopper's choices against this product.
     *
     * @return array{metal?: string, size?: string, size_label?: string, engraving?: string}
     *
     * @throws ValidationException
     */
    public function resolveOptions(array $input): array
    {
        $options = [];
        $errors = [];

        if ($metals = $this->metals()) {
            $metal = trim((string) ($input['metal'] ?? ''));
            if (! in_array($metal, array_column($metals, 'name'), true)) {
                $errors['metal'] = 'Please choose a metal.';
            } else {
                $options['metal'] = $metal;
            }
        }

        if ($sizes = $this->sizes()) {
            $size = trim((string) ($input['size'] ?? ''));
            if (! in_array($size, $sizes, true)) {
                $errors['size'] = 'Please choose a '.Str::lower($this->size_title).'.';
            } else {
                $options['size'] = $size;
                $options['size_label'] = $this->size_title;
            }
        }

        $engraving = trim((string) ($input['engraving'] ?? ''));
        if ($engraving !== '') {
            if (! $this->engraving_enabled) {
                $errors['engraving'] = 'This piece cannot be engraved.';
            } elseif (mb_strlen($engraving) > self::ENGRAVING_MAX) {
                $errors['engraving'] = 'Engraving can be at most '.self::ENGRAVING_MAX.' characters.';
            } else {
                $options['engraving'] = $engraving;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $options;
    }

    /** Price of one piece with the chosen options applied. */
    public function unitPriceFor(array $options): float
    {
        $price = $this->current_price;

        if (isset($options['metal'])) {
            foreach ($this->metals() as $metal) {
                if ($metal['name'] === $options['metal']) {
                    $price += $metal['price'];
                }
            }
        }

        if (isset($options['engraving']) && $this->engraving_enabled) {
            $price += (float) $this->engraving_fee;
        }

        return round(max(0, $price), 2);
    }

    /**
     * Human-readable lines for stored options, e.g. ["Rose gold", "Ring size 7", "Engraved “A & J”"].
     *
     * @return array<int, string>
     */
    public static function describeOptions(?array $options): array
    {
        $options ??= [];
        $lines = [];

        if (! empty($options['metal'])) {
            $lines[] = $options['metal'];
        }
        if (! empty($options['size'])) {
            $lines[] = ($options['size_label'] ?? 'Size').' '.$options['size'];
        }
        if (! empty($options['engraving'])) {
            $lines[] = 'Engraved “'.$options['engraving'].'”';
        }

        return $lines;
    }

    public function refreshRating(): void
    {
        $approved = $this->approvedReviews();
        $this->forceFill([
            'rating_avg' => round((float) $approved->avg('rating'), 2),
            'reviews_count' => $approved->count(),
        ])->save();
    }

    public static function resolveImage(?string $path, string $label = 'Lax Jewellers'): string
    {
        if (! $path) {
            return 'https://placehold.co/800x800?text='.urlencode($label);
        }

        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset('storage/'.$path);
    }
}
