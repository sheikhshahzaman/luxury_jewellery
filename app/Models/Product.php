<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    /**
     * SQL for the effective (sale-aware) price.
     * CAST keeps the comparison numeric — SQLite's COALESCE otherwise strips
     * column affinity, which makes string-bound range values compare wrong.
     */
    public const EFFECTIVE_PRICE_SQL = 'CAST(COALESCE(sale_price, price) AS DECIMAL(10,2))';

    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'description', 'details',
        'price', 'sale_price', 'stock', 'image', 'tag',
        'is_featured', 'is_active', 'rating_avg', 'reviews_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'rating_avg' => 'decimal:2',
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

    public function refreshRating(): void
    {
        $approved = $this->approvedReviews();
        $this->forceFill([
            'rating_avg' => round((float) $approved->avg('rating'), 2),
            'reviews_count' => $approved->count(),
        ])->save();
    }

    public static function resolveImage(?string $path, string $label = 'Eluxee'): string
    {
        if (! $path) {
            return 'https://placehold.co/800x800?text='.urlencode($label);
        }

        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset('storage/'.$path);
    }
}
