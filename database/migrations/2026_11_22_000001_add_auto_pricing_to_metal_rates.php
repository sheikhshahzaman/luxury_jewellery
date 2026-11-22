<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each buy/sell price can now follow the market: "auto" items are worked
     * out from the spot price, the dollar rate and the item's weight, plus a
     * margin (percent over spot) for buy and for sell. "manual" items stay
     * exactly as typed in Admin.
     */
    public function up(): void
    {
        Schema::table('metal_rates', function (Blueprint $table) {
            $table->string('pricing', 10)->default('manual')->after('quantity'); // manual | auto
            $table->decimal('buy_margin', 7, 3)->default(0)->after('pricing');   // percent over spot
            $table->decimal('sell_margin', 7, 3)->default(0)->after('buy_margin');
        });

        // The board was typed in once, on 5 Oct 2026, and never followed the market
        // afterwards. Items that still hold those prices switch to auto; anything
        // the admin has changed since stays manual.
        //  - Gold: margins that match the Karachi Sarafa 24K rate of 22 Nov 2026
        //    (Rs 385,000 / 384,000 per tola) at Kitco spot $3,537.90 and Rs 276.5960
        //    per dollar. Sarafa prices smaller sizes by weight, so all sizes share them.
        //  - Silver: no reliable local rate was published, so each item keeps the
        //    margin over spot it had on 5 Oct (silver $60.68, Rs 276.89 per dollar).
        $goldMargins = [4.915, 4.643];
        $original = [
            'gold_1_tola' => [438993, 434990],
            'gold_10_gram' => [376389, 372958],
            'gold_5_gram' => [188183, 186469],
            'gold_1_gram' => [37632, 37303],
            'silver_10_tola_qr' => [72010, 67998],
            'silver_10_tola_999' => [70997, 67001],
            'silver_1_kg' => [595825, 574398],
            'silver_5_tola_bar' => [36752, 33494],
            'silver_1_tola_bar' => [7996, 6994],
        ];
        $spotThen = ['gold' => 4143.60, 'silver' => 60.68];
        $pkrThen = 276.89;
        $grams = ['tola' => 11.6638, 'gram' => 1, 'kg' => 1000, 'oz' => 31.1035];

        foreach (DB::table('metal_rates')->get() as $row) {
            [$buy, $sell] = $original[$row->key] ?? [null, null];
            if ($buy === null || (float) $row->buy != $buy || (float) $row->sell != $sell || ! isset($spotThen[$row->metal], $grams[$row->unit])) {
                continue;
            }
            $atSpot = $spotThen[$row->metal] / 31.1035 * $grams[$row->unit] * (float) $row->quantity * $pkrThen;

            // Query builder on purpose: updated_at keeps saying when the admin last typed a price.
            [$buyMargin, $sellMargin] = $row->metal === 'gold'
                ? $goldMargins
                : [round(($buy / $atSpot - 1) * 100, 3), round(($sell / $atSpot - 1) * 100, 3)];
            DB::table('metal_rates')->where('id', $row->id)->update([
                'pricing' => 'auto',
                'buy_margin' => $buyMargin,
                'sell_margin' => $sellMargin,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('metal_rates', function (Blueprint $table) {
            $table->dropColumn(['pricing', 'buy_margin', 'sell_margin']);
        });
    }
};
