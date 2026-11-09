<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payment account shown at checkout: a bank account or a mobile wallet
 * (JazzCash / Easypaisa). For wallets, account_number holds the wallet number.
 */
class BankAccount extends Model
{
    public const TYPES = [
        'bank' => 'Bank transfer',
        'jazzcash' => 'JazzCash',
        'easypaisa' => 'Easypaisa',
    ];

    protected $fillable = [
        'type', 'bank_name', 'account_name', 'account_number', 'iban',
        'swift_code', 'branch', 'instructions', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('bank_name');
    }

    public function isWallet(): bool
    {
        return $this->type !== 'bank';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    /** "Meezan Bank" for banks, "JazzCash" for wallets when no name was given. */
    public function getDisplayNameAttribute(): string
    {
        return $this->bank_name ?: $this->type_label;
    }
}
