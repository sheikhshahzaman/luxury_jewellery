<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The store sells gold and silver jewellery without stones, so the theme's
 * original copy that mentions stones is reworded. Only values that still
 * match the original theme text are changed; anything the admin has
 * written themselves is left alone.
 */
return new class extends Migration
{
    protected array $changes = [
        'store_tagline' => [
            'Fine jewelry handcrafted in small batches since 1999. Recycled gold, traceable stones, lifetime care.',
            'Fine jewelry handcrafted in small batches since 1999. Recycled gold, made to last, lifetime care.',
        ],
        'banner_text' => [
            'Warm gold tones, soft silhouettes, and stones chosen for evening light. A limited run of 200 numbered pieces.',
            'Warm gold tones and soft silhouettes, made for evening light. A limited run of 200 numbered pieces.',
        ],
        'value_1_text' => [
            'Traceable stones and certified recycled 18k gold in every piece.',
            'Certified recycled 18k gold in every piece.',
        ],
    ];

    public function up(): void
    {
        foreach ($this->changes as $key => [$old, $new]) {
            DB::table('settings')->where('key', $key)->where('value', $old)->update(['value' => $new]);
        }
    }

    public function down(): void
    {
        foreach ($this->changes as $key => [$old, $new]) {
            DB::table('settings')->where('key', $key)->where('value', $new)->update(['value' => $old]);
        }
    }
};
