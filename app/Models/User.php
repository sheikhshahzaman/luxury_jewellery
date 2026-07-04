<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'is_blocked',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlist(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlists')->withTimestamps();
    }

    /** Request-memoised set of wishlisted product ids (avoids N queries in grids). */
    protected ?array $wishlistIdsCache = null;

    public function wishlistProductIds(): array
    {
        return $this->wishlistIdsCache ??= $this->wishlist()->pluck('products.id')->all();
    }

    public function hasInWishlist(Product $product): bool
    {
        return in_array($product->id, $this->wishlistProductIds(), true);
    }

    public function hasPurchased(Product $product): bool
    {
        return $this->orders()
            ->where('status', '!=', 'cancelled')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();
    }
}
