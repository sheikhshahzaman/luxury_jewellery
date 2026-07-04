<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id', 'label', 'name', 'phone', 'address', 'city',
        'state', 'postal_code', 'country', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getOneLineAttribute(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->postal_code, $this->country])
            ->filter()->implode(', ');
    }
}
