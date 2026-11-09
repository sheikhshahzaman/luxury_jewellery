<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // [{"name": "Yellow gold", "price": 0}, {"name": "Rose gold", "price": 20}]
            $table->json('metal_options')->nullable()->after('stock');
            // ["5", "6", "7"] — ring sizes, chain lengths…
            $table->json('size_options')->nullable()->after('metal_options');
            $table->string('size_label')->nullable()->after('size_options');
            $table->boolean('engraving_enabled')->default(false)->after('size_label');
            $table->decimal('engraving_fee', 10, 2)->default(0)->after('engraving_enabled');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('options')->nullable()->after('product_image');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['metal_options', 'size_options', 'size_label', 'engraving_enabled', 'engraving_fee']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
