<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_name', 'product_image', 'options',
        'price', 'quantity', 'line_total',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'options' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<int, string> */
    public function getOptionLinesAttribute(): array
    {
        return Product::describeOptions($this->options);
    }

    public function getImageUrlAttribute(): string
    {
        return Product::resolveImage($this->product_image, $this->product_name);
    }
}
