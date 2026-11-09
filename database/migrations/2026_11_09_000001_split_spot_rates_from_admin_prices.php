<?php

use App\Models\MetalRate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two kinds of rates, kept apart:
     *  - spot_rates: international spot prices fetched on a schedule (or typed
     *    in by the admin in manual mode), one row per instrument per fetch.
     *  - metal_rates: the store's own buy/sell prices, only ever set in Admin,
     *    now with a stable key, unit and quantity, plus an audit trail.
     */
    public function up(): void
    {
        Schema::create('spot_rates', function (Blueprint $table) {
            $table->id();
            $table->string('instrument', 12); // XAU | XAG | USDPKR
            $table->decimal('bid', 14, 4);
            $table->decimal('ask', 14, 4);
            $table->string('source', 40); // provider name, or "manual"
            $table->timestamp('fetched_at');
            $table->index(['instrument', 'fetched_at']); // newest per instrument without a full scan
        });

        Schema::table('metal_rates', function (Blueprint $table) {
            $table->string('key', 60)->nullable()->after('id');
            $table->string('unit', 10)->default('tola')->after('note'); // tola | gram | kg | oz
            $table->decimal('quantity', 10, 3)->default(1)->after('unit');
            $table->foreignId('updated_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
        });

        Schema::create('metal_rate_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metal_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 60); // kept even if the item is later removed
            $table->decimal('buy_before', 12, 2)->nullable();
            $table->decimal('sell_before', 12, 2)->nullable();
            $table->decimal('buy', 12, 2);
            $table->decimal('sell', 12, 2);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['metal_rate_id', 'created_at']);
        });

        // Give existing rows a key, unit and quantity, read from their label ("10 Tola", "1 KG").
        $taken = [];
        foreach (DB::table('metal_rates')->orderBy('id')->get() as $row) {
            [$quantity, $unit] = MetalRate::parseSize($row->label);
            $key = MetalRate::makeKey($row->metal, $quantity, $unit, $row->note, $taken);
            $taken[] = $key;
            DB::table('metal_rates')->where('id', $row->id)->update(compact('key', 'unit', 'quantity'));
        }

        Schema::table('metal_rates', function (Blueprint $table) {
            $table->unique('key');
        });

        // Start the spot history from the values the admin had set, so the
        // page has numbers before the first scheduled fetch.
        $now = now();
        foreach (['XAU' => ['spot_gold_usd', 4143.60], 'XAG' => ['spot_silver_usd', 60.68]] as $instrument => [$setting, $default]) {
            $value = (float) (DB::table('settings')->where('key', $setting)->value('value') ?: $default);
            DB::table('spot_rates')->insert(['instrument' => $instrument, 'bid' => $value, 'ask' => $value, 'source' => 'manual', 'fetched_at' => $now]);
        }

        // The old simulated movement is gone; its settings are no longer read.
        DB::table('settings')->whereIn('key', ['rates_swing_min', 'rates_swing_max', 'rates_tick_seconds'])->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_rate_changes');
        Schema::table('metal_rates', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['key', 'unit', 'quantity']);
        });
        Schema::dropIfExists('spot_rates');
    }
};
